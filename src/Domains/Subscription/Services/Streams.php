<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use JayI\Impex\Domains\Subscription\Contracts\Stream;
use JayI\Impex\Domains\Subscription\Enums\EventKind;
use JayI\Impex\Domains\Subscription\Enums\StreamKind;
use JayI\Impex\Domains\Subscription\Exceptions\SubscriptionException;
use JayI\Impex\Domains\Subscription\Support\TopicMask;

/**
 * Where a package reports change. Both entry points are cheap enough for a
 * request: one write, nothing per subscriber, no HTTP. The work happens on
 * the queue.
 */
final class Streams
{
    public function __construct(
        private readonly StreamRegistry $registry,
        private readonly ConnectionInterface $db,
        private readonly SubscriptionJobs $jobs,
    ) {}

    /**
     * @param  Stream|class-string<Stream>  $stream
     */
    public function register(Stream|string $stream): Stream
    {
        return $this->registry->register($stream);
    }

    public function get(string $key): Stream
    {
        return $this->registry->get($key);
    }

    /**
     * @return array<string, Stream>
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    /**
     * Say that subjects of a snapshot stream may have changed.
     *
     * Touch freely: a subject touched a thousand times before it is looked at
     * is looked at once, and one that ends up as it was sends nothing. Inside
     * a transaction, the touch commits or rolls back with it.
     *
     * @param  iterable<int, string|int>  $keys
     */
    public function touch(string $stream, iterable $keys): void
    {
        if ($this->registry->get($stream)->kind() !== StreamKind::Snapshot) {
            throw SubscriptionException::notSnapshot($stream);
        }

        $now = Carbon::now();
        // A fresh revision on every touch, so the detector can tell a subject
        // touched again while it was being looked at.
        $revision = random_int(1, PHP_INT_MAX);
        $touched = false;
        $rows = [];

        foreach ($keys as $key) {
            $rows[(string) $key] = ['stream' => $stream, 'subject_key' => (string) $key, 'revision' => $revision, 'touched_at' => $now];

            if (count($rows) === 1000) {
                $this->upsertTouches($rows);
                $rows = [];
                $touched = true;
            }
        }

        if ($rows !== []) {
            $this->upsertTouches($rows);
            $touched = true;
        }

        if ($touched) {
            $this->jobs->detect($stream);
        }
    }

    /**
     * Publish a discrete event to an append stream: an order placed, a
     * shipment dispatched. Each one is delivered as it is, in order.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $topics  Empty means every topic.
     */
    public function publish(string $stream, string $subjectKey, string $name, array $payload = [], array $topics = []): int
    {
        $definition = $this->registry->get($stream);

        if ($definition->kind() !== StreamKind::Append) {
            throw SubscriptionException::notAppend($stream);
        }

        $mask = $topics === [] ? TopicMask::all($definition->topics()) : TopicMask::of($definition->topics(), $topics);

        $id = (int) $this->db->table('impex_events')->insertGetId([
            'stream' => $stream,
            'subject_key' => $subjectKey,
            'kind' => EventKind::Appended->value,
            'name' => $name,
            'topics' => $mask,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'occurred_at' => Carbon::now(),
        ]);

        $this->jobs->detect($stream);

        return $id;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function upsertTouches(array $rows): void
    {
        $this->db->table('impex_stream_touches')->upsert(array_values($rows), ['stream', 'subject_key'], ['touched_at', 'revision']);
    }
}
