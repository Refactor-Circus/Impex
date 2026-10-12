<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RefactorCircus\Impex\Domains\Subscription\Contracts\Stream;
use RefactorCircus\Impex\Domains\Subscription\Data\StreamEvent;
use RefactorCircus\Impex\Domains\Subscription\Enums\EventKind;
use RefactorCircus\Impex\Domains\Subscription\Enums\Selection;
use RefactorCircus\Impex\Domains\Subscription\Enums\StreamKind;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Support\TopicMask;
use RefactorCircus\Impex\Support\Tables;
use stdClass;

/**
 * Decides who hears about each event, and writes one narrow row per
 * subscriber it matters to.
 *
 * Subscriptions are matched by their rules, never by expanding them: a
 * subscription to a category of two million products costs this nothing
 * until one of those products changes. Explicit lists are the exception, and
 * are looked up only for the keys in hand.
 *
 * A subject that leaves a subscription's scope — moved out of its category,
 * say — is sent to that subscriber as removed, so its copy does not keep a
 * product it should no longer have.
 */
final class FanOut
{
    private const int PENDING_REFRESH_SECONDS = 5;

    /**
     * @var array<string, array{at: float, subscriptions: list<SubscriptionModel>}>
     */
    private array $subscriptions = [];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Config $config,
        private readonly SubscriptionJobs $jobs,
        private readonly Holdings $holdings,
    ) {}

    /**
     * @param  list<StreamEvent>  $events  Already written, with ids.
     * @param  array<string, array<string, mixed>>  $snapshots  The subjects as
     *                                                          they are, for the matcher. Removed ones are absent.
     * @param  list<string>  $rescoped  Subjects that changed only outside
     *                                  the topics, which may have moved into or out of scope.
     * @return list<string> The subscriptions that got something.
     */
    public function dispatch(Stream $stream, array $events, array $snapshots, array $rescoped = []): array
    {
        $subscriptions = $this->subscriptionsFor($stream->key());

        if (($events === [] && $rescoped === []) || $subscriptions === []) {
            return [];
        }

        $snapshot = $stream->kind() === StreamKind::Snapshot;
        $keys = array_values(array_unique([...array_map(fn (StreamEvent $e): string => $e->subjectKey, $events), ...$rescoped]));
        $inScope = $this->scope($stream, $subscriptions, $keys, $snapshots);

        $byId = [];
        $scoped = [];
        $everything = [];

        foreach ($subscriptions as $subscription) {
            $byId[$subscription->id] = $subscription;

            // Only a filtered or listed subscription can gain or lose a
            // subject; one that takes everything never does, so its history
            // is never read — at catalogue scale that is most of it.
            if ($subscription->selection === Selection::All) {
                $everything[$subscription->id] = true;
            } else {
                $scoped[$subscription->id] = true;
            }
        }

        $prior = $snapshot && $scoped !== []
            ? $this->holdings->of($keys, array_map('strval', array_keys($scoped)))
            : [];
        $held = [];
        $released = [];

        $rows = [];
        $entering = [];
        $leaving = [];
        $present = fn (string $key): int => $this->presentTopics($stream, $snapshots[$key] ?? null);

        foreach ($events as $event) {
            $key = $event->subjectKey;
            $known = array_intersect_key($prior[$key] ?? [], $byId);

            // A subject that is gone: everyone who had it, and every
            // subscription that takes everything, which may have.
            if ($event->kind === EventKind::Removed) {
                foreach (array_keys($known + $everything) as $id) {
                    $rows[] = ['subscription_id' => $id, 'event_id' => $event->id];
                }

                foreach (array_keys($known) as $id) {
                    $released[$id][] = $key;
                }

                continue;
            }

            $routed = [];

            foreach (array_keys($inScope[$key] ?? []) as $id) {
                if (($event->topics & $byId[$id]->topics) !== 0) {
                    $rows[] = ['subscription_id' => $id, 'event_id' => $event->id];
                    $routed[$id] = true;

                    if ($snapshot && isset($scoped[$id])) {
                        $held[$id][] = $key;
                    }
                }
            }

            if ($snapshot) {
                $this->rescope($key, array_intersect_key($inScope[$key] ?? [], $scoped), $routed, $known, $present($key), $byId, $entering, $leaving);
            }
        }

        foreach ($snapshot ? $rescoped : [] as $key) {
            $this->rescope($key, array_intersect_key($inScope[$key] ?? [], $scoped), [], array_intersect_key($prior[$key] ?? [], $byId), $present($key), $byId, $entering, $leaving);
        }

        $rows = [
            ...$rows,
            ...$this->targeted($stream, EventKind::Changed, 'entered_scope', $entering),
            ...$this->targeted($stream, EventKind::Removed, 'left_scope', $leaving),
        ];

        foreach ($entering as $key => $ids) {
            foreach ($ids as $id) {
                $held[$id][] = (string) $key;
            }
        }

        foreach ($leaving as $key => $ids) {
            foreach ($ids as $id) {
                $released[$id][] = (string) $key;
            }
        }

        if ($held !== [] || $released !== []) {
            $this->holdings->apply($held, $released);
        }

        return $this->write($rows);
    }

    /**
     * Who a subject just reached, and who it just left. A subscriber reached
     * gets the whole subject, in every topic it follows, since it never had
     * it — unless the subject has nothing in those topics, which would tell
     * it nothing. One left behind is told to forget it.
     *
     * @param  array<string, true>  $inScope
     * @param  array<string, true>  $routed  Sent this subject's event already.
     * @param  array<string, true>  $known  Already have the subject.
     * @param  int  $present  The topics the subject has anything in.
     * @param  array<string, SubscriptionModel>  $byId
     * @param  array<string, list<string>>  $entering
     * @param  array<string, list<string>>  $leaving
     */
    private function rescope(string $key, array $inScope, array $routed, array $known, int $present, array $byId, array &$entering, array &$leaving): void
    {
        foreach (array_keys(array_diff_key($inScope, $routed, $known)) as $id) {
            if (($present & $byId[$id]->topics) !== 0) {
                $entering[$key][] = (string) $id;
            }
        }

        foreach (array_keys(array_diff_key($known, $inScope)) as $id) {
            $leaving[$key][] = (string) $id;
        }
    }

    /**
     * The bits of the topics a subject has anything in.
     *
     * @param  array<string, mixed>|null  $snapshot
     */
    private function presentTopics(Stream $stream, ?array $snapshot): int
    {
        if ($snapshot === null) {
            return 0;
        }

        if ($stream->kind() === StreamKind::Append) {
            return TopicMask::all($stream->topics());
        }

        $slices = $stream->slice($snapshot);
        $mask = 0;

        foreach ($stream->topics() as $bit => $topic) {
            if (($slices[$topic] ?? null) !== null) {
                $mask |= 1 << $bit;
            }
        }

        return $mask;
    }

    /**
     * Forget the cached subscriptions, so the next chunk sees a change.
     */
    public function flush(): void
    {
        $this->subscriptions = [];
    }

    /**
     * Which subscriptions each key falls in, regardless of topic.
     *
     * @param  list<SubscriptionModel>  $subscriptions
     * @param  list<string>  $keys
     * @param  array<string, array<string, mixed>>  $snapshots
     * @return array<string, array<string, true>> Subscription ids, by key.
     */
    private function scope(Stream $stream, array $subscriptions, array $keys, array $snapshots): array
    {
        $scope = [];
        $filtered = [];
        $listed = [];

        foreach ($subscriptions as $subscription) {
            if ($subscription->selection === Selection::Filter) {
                $filtered[] = $subscription;
            } elseif ($subscription->selection === Selection::List) {
                $listed[] = $subscription->id;
            } else {
                foreach ($keys as $key) {
                    if (array_key_exists($key, $snapshots)) {
                        $scope[$key][$subscription->id] = true;
                    }
                }
            }
        }

        if ($filtered !== [] && $snapshots !== []) {
            foreach ($stream->matcher()->match($snapshots, $filtered) as $id => $matched) {
                foreach ($matched as $key) {
                    $scope[$key][$id] = true;
                }
            }
        }

        if ($listed !== []) {
            $present = array_values(array_intersect($keys, array_map('strval', array_keys($snapshots))));

            foreach (array_chunk($present, 1000) as $chunk) {
                $this->db->table(Tables::name('subscription_subjects'))
                    ->whereIn('subscription_id', $listed)
                    ->whereIn('subject_key', $chunk)
                    ->get(['subscription_id', 'subject_key'])
                    ->each(function (mixed $row) use (&$scope): void {
                        /** @var stdClass $row */
                        $scope[(string) $row->subject_key][(string) $row->subscription_id] = true;
                    });
            }
        }

        return $scope;
    }

    /**
     * One event per subject, routed only to the subscriptions listed for it:
     * a removal for those a subject left, the whole subject for those it
     * reached.
     *
     * @param  array<string, list<string>>  $targets  Subscription ids, by key.
     * @return list<array{subscription_id: string, event_id: int}>
     */
    private function targeted(Stream $stream, EventKind $kind, string $name, array $targets): array
    {
        if ($targets === []) {
            return [];
        }

        $token = strtolower((string) Str::ulid());
        $now = Carbon::now();

        $this->db->table(Tables::name('events'))->insert(array_map(fn (string $key): array => [
            'stream' => $stream->key(),
            'subject_key' => $key,
            'kind' => $kind->value,
            'name' => $name,
            'topics' => TopicMask::all($stream->topics()),
            'payload' => null,
            'batch' => $token,
            'fanned_out_at' => $now,
            'occurred_at' => $now,
        ], array_map('strval', array_keys($targets))));

        /** @var iterable<int, stdClass> $events */
        $events = $this->db->table(Tables::name('events'))->where('batch', $token)->get(['id', 'subject_key']);

        $rows = [];

        foreach ($events as $event) {
            foreach ($targets[(string) $event->subject_key] ?? [] as $id) {
                $rows[] = ['subscription_id' => $id, 'event_id' => (int) $event->id];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{subscription_id: string, event_id: int}>  $rows
     * @return list<string>
     */
    private function write(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            $this->db->table(Tables::name('subscription_events'))->insertOrIgnore($chunk);
        }

        $ids = array_values(array_unique(array_column($rows, 'subscription_id')));

        // Marked only when unmarked or marked a while ago: a backlog fanned
        // out chunk after chunk would otherwise rewrite every subscription's
        // row each time. A delivery pass clears a mark only once nothing is
        // left past its cursor, so a fresh mark is never lost to one.
        $now = Carbon::now();
        $stale = $now->copy()->subSeconds(self::PENDING_REFRESH_SECONDS);

        foreach (array_chunk($ids, 1000) as $chunk) {
            $this->db->table(Tables::name('subscriptions'))
                ->whereIn('id', $chunk)
                ->where(fn (Builder $q) => $q->whereNull('pending_at')->orWhere('pending_at', '<', $stale))
                ->update(['pending_at' => $now]);
        }

        foreach ($ids as $id) {
            $this->jobs->deliver($id);
        }

        return $ids;
    }

    /**
     * Every subscription to the stream that collects events — paused and
     * disabled ones too, so resuming picks up where they left off. Held for
     * `impex.subscriptions.cache_seconds`, since a detection pass asks for
     * them once per chunk.
     *
     * @return list<SubscriptionModel>
     */
    private function subscriptionsFor(string $stream): array
    {
        /** @var int $ttl */
        $ttl = $this->config->get('impex.subscriptions.cache_seconds', 30);
        $cached = $this->subscriptions[$stream] ?? null;

        if ($cached !== null && (microtime(true) - $cached['at']) < $ttl) {
            return $cached['subscriptions'];
        }

        $subscriptions = SubscriptionModel::query()->where('stream', $stream)->get()->all();

        $this->subscriptions[$stream] = ['at' => microtime(true), 'subscriptions' => array_values($subscriptions)];

        return $this->subscriptions[$stream]['subscriptions'];
    }
}
