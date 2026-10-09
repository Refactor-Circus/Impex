<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RefactorCircus\Impex\Domains\Subscription\Contracts\Stream;
use RefactorCircus\Impex\Domains\Subscription\Data\StreamEvent;
use RefactorCircus\Impex\Domains\Subscription\Enums\EventKind;
use RefactorCircus\Impex\Domains\Subscription\Enums\StreamKind;
use RefactorCircus\Impex\Domains\Subscription\Support\TopicMask;
use stdClass;

/**
 * Works out what actually changed, a chunk of subjects at a time.
 *
 * For a snapshot stream: claim a chunk of touched subjects, load them in one
 * call, hash each topic's slice, and compare with the hashes from last time.
 * Only topics whose hash moved become an event, so an import that rewrites
 * ten million products with the values they already had sends nothing.
 *
 * Every query here is per chunk, not per subject, and chunks are claimed
 * with a lease, so any number of workers can share a backlog.
 */
final class Detector
{
    public function __construct(
        private readonly StreamRegistry $streams,
        private readonly FanOut $fanOut,
        private readonly ConnectionInterface $db,
        private readonly Config $config,
    ) {}

    /**
     * Process one chunk. Returns how many subjects or events it handled; zero
     * means the backlog is empty, or another worker holds what is left.
     */
    public function run(string $streamKey): int
    {
        $stream = $this->streams->get($streamKey);

        return $stream->kind() === StreamKind::Append
            ? $this->fanOutAppended($stream)
            : $this->detect($stream);
    }

    /**
     * How long one job keeps claiming chunks before handing over to the next.
     */
    public function timeBudget(): int
    {
        /** @var int $budget */
        $budget = $this->config->get('impex.subscriptions.detection.time_budget', 50);

        return $budget;
    }

    private function detect(Stream $stream): int
    {
        $token = strtolower((string) Str::ulid());
        $keys = $this->claim($stream->key(), $token);

        if ($keys === []) {
            return 0;
        }

        $snapshots = $stream->snapshots($keys);
        $states = $this->db->table('impex_subject_states')
            ->where('stream', $stream->key())
            ->whereIn('subject_key', $keys)
            ->pluck('hashes', 'subject_key')
            ->all();

        $topics = $stream->topics();
        $now = Carbon::now();
        $events = [];
        $upserts = [];
        $removed = [];
        $rescoped = [];

        foreach ($keys as $key) {
            $snapshot = $snapshots[$key] ?? null;
            $previous = isset($states[$key]) ? (string) $states[$key] : null;

            if ($snapshot === null) {
                if ($previous !== null) {
                    $events[] = $this->row($stream->key(), $key, EventKind::Removed, TopicMask::all($topics), $token, $now);
                    $removed[] = $key;
                }

                continue;
            }

            [$hashes, $changed, $elsewhere] = $this->compare($stream, $snapshot, $previous);

            if ($changed !== 0) {
                $events[] = $this->row($stream->key(), $key, EventKind::Changed, $changed, $token, $now);
            } elseif ($elsewhere) {
                // Nothing a subscriber sees changed, but something did — a
                // category, an owner — which may move the subject into or out
                // of a subscription's scope.
                $rescoped[] = $key;
            }

            if ($hashes !== $previous) {
                $upserts[] = ['stream' => $stream->key(), 'subject_key' => $key, 'hashes' => $hashes, 'updated_at' => $now];
            }
        }

        // One transaction per chunk: the events, the new hashes, the fan-out
        // and the release of the touches land together or not at all, so a
        // worker killed halfway leaves the chunk to be claimed again rather
        // than half-sent.
        $this->db->transaction(function () use ($stream, $events, $upserts, $removed, $rescoped, $token, $snapshots): void {
            foreach (array_chunk($events, 500) as $chunk) {
                $this->db->table('impex_events')->insert($chunk);
            }

            foreach (array_chunk($upserts, 500) as $chunk) {
                $this->db->table('impex_subject_states')->upsert($chunk, ['stream', 'subject_key'], ['hashes', 'updated_at']);
            }

            if ($removed !== []) {
                $this->db->table('impex_subject_states')
                    ->where('stream', $stream->key())
                    ->whereIn('subject_key', $removed)
                    ->delete();
            }

            if ($events !== [] || $rescoped !== []) {
                /** @var array<string, array<string, mixed>> $present */
                $present = array_filter($snapshots, fn (?array $snapshot): bool => $snapshot !== null);

                $this->fanOut->dispatch($stream, $this->inserted($token), $present, $rescoped);
            }

            $this->release($stream->key(), $token);
        });

        return count($keys);
    }

    /**
     * Fan out an append stream's newly published events, oldest first.
     */
    private function fanOutAppended(Stream $stream): int
    {
        $token = strtolower((string) Str::ulid());

        $ids = $this->db->table('impex_events')
            ->where('stream', $stream->key())
            ->whereNull('fanned_out_at')
            ->orderBy('id')
            ->limit($this->chunk())
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        // Claimed by tagging: the batch column is only ever set once, so two
        // workers cannot fan the same event out twice.
        $this->db->table('impex_events')
            ->whereIn('id', $ids)
            ->whereNull('fanned_out_at')
            ->whereNull('batch')
            ->update(['batch' => $token]);

        $events = $this->inserted($token);

        if ($events === []) {
            return 0;
        }

        $this->db->transaction(function () use ($stream, $events, $token): void {
            $payloads = [];

            foreach ($events as $event) {
                $payloads[$event->subjectKey] = $event->payload ?? [];
            }

            $this->fanOut->dispatch($stream, $events, $payloads);

            $this->db->table('impex_events')->where('batch', $token)->update(['fanned_out_at' => Carbon::now()]);
        });

        return count($events);
    }

    /**
     * Lease up to a chunk of touched subjects to this worker. The update
     * re-checks the lease, so of two workers selecting the same keys, each
     * key goes to one of them.
     *
     * @return list<string>
     */
    private function claim(string $stream, string $token): array
    {
        $now = Carbon::now();
        $claimable = function (Builder $query) use ($now): void {
            $query->whereNull('leased_until')->orWhere('leased_until', '<', $now);
        };

        $keys = $this->db->table('impex_stream_touches')
            ->where('stream', $stream)
            ->where($claimable)
            ->orderBy('touched_at')
            ->limit($this->chunk())
            ->pluck('subject_key')
            ->all();

        if ($keys === []) {
            return [];
        }

        /** @var int $lease */
        $lease = $this->config->get('impex.subscriptions.detection.lease_seconds', 300);

        $this->db->table('impex_stream_touches')
            ->where('stream', $stream)
            ->whereIn('subject_key', $keys)
            ->where($claimable)
            ->update([
                'lease_token' => $token,
                'leased_until' => $now->copy()->addSeconds($lease),
                'claimed_revision' => new Expression('revision'),
            ]);

        /** @var list<string> $claimed */
        $claimed = $this->db->table('impex_stream_touches')
            ->where('lease_token', $token)
            ->pluck('subject_key')
            ->map(fn (mixed $key): string => (string) $key)
            ->all();

        return $claimed;
    }

    /**
     * Drop the touches this chunk handled. One touched again while it was
     * being looked at keeps its row, released for the next pass.
     */
    private function release(string $stream, string $token): void
    {
        $this->db->table('impex_stream_touches')
            ->where('lease_token', $token)
            ->whereColumn('revision', '=', 'claimed_revision')
            ->delete();

        $this->db->table('impex_stream_touches')
            ->where('stream', $stream)
            ->where('lease_token', $token)
            ->update(['lease_token' => null, 'leased_until' => null, 'claimed_revision' => null]);
    }

    /**
     * The snapshot's hashes — one per topic, then one of the whole snapshot —
     * the bits of the topics that differ from the previous ones, and whether
     * anything outside the topics changed. A subject seen for the first time
     * changed in every topic it has something in.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{0: string, 1: int, 2: bool}
     */
    private function compare(Stream $stream, array $snapshot, ?string $previous): array
    {
        $slices = $stream->slice($snapshot);
        $topics = $stream->topics();
        $hashes = '';
        $changed = 0;

        foreach ($topics as $bit => $topic) {
            $slice = $slices[$topic] ?? null;
            $hash = $this->hash($slice);
            $hashes .= $hash;

            $differs = $previous === null
                ? $slice !== null
                : substr($previous, $bit * 16, 16) !== $hash;

            if ($differs) {
                $changed |= 1 << $bit;
            }
        }

        $whole = $this->hash($snapshot);
        $elsewhere = $previous !== null && substr($previous, count($topics) * 16, 16) !== $whole;

        return [$hashes.$whole, $changed, $elsewhere];
    }

    private function hash(mixed $value): string
    {
        return hash('xxh3', (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $stream, string $key, EventKind $kind, int $topics, string $token, Carbon $now): array
    {
        return [
            'stream' => $stream,
            'subject_key' => $key,
            'kind' => $kind->value,
            'name' => null,
            'topics' => $topics,
            'payload' => null,
            'batch' => $token,
            'fanned_out_at' => $now,
            'occurred_at' => $now,
        ];
    }

    /**
     * The events a batch wrote, read back with their ids: a bulk insert does
     * not return them.
     *
     * @return list<StreamEvent>
     */
    private function inserted(string $token): array
    {
        return array_values($this->db->table('impex_events')
            ->where('batch', $token)
            ->orderBy('id')
            ->get()
            ->map(function (mixed $row): StreamEvent {
                /** @var stdClass $row */
                return StreamEvent::fromRow($row);
            })
            ->all());
    }

    private function chunk(): int
    {
        /** @var int $chunk */
        $chunk = $this->config->get('impex.subscriptions.detection.chunk', 1000);

        return $chunk;
    }
}
