<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Services;

use Illuminate\Database\ConnectionInterface;
use RefactorCircus\Impex\Domains\Subscription\Data\StreamEvent;
use RefactorCircus\Impex\Domains\Subscription\Enums\EventKind;
use RefactorCircus\Impex\Domains\Subscription\Enums\StreamKind;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use stdClass;

/**
 * Reads a subscription's events forward from a cursor, and turns them into
 * what the subscriber receives. Delivery and the feed both read through
 * here, so a pushed batch and a pulled page of the same events are the same.
 */
final class EventReader
{
    public function __construct(
        private readonly StreamRegistry $streams,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * Up to $limit events after $after, oldest first.
     *
     * Two queries, each a primary-key read, rather than one join: a planner
     * free to choose may drive a join from the events table instead, scanning
     * every event after the cursor — every subscription's — to keep this
     * one's hundred. MySQL does exactly that near the start of a cursor, and
     * the cost grows with the whole stream.
     *
     * @return list<StreamEvent>
     */
    public function read(SubscriptionModel $subscription, int $after, int $limit): array
    {
        $ids = $this->db->table('impex_subscription_events')
            ->where('subscription_id', $subscription->id)
            ->where('event_id', '>', $after)
            ->orderBy('event_id')
            ->limit($limit)
            ->pluck('event_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return [];
        }

        return array_values($this->db->table('impex_events')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'stream', 'subject_key', 'kind', 'name', 'topics', 'payload', 'occurred_at'])
            ->map(function (mixed $row): StreamEvent {
                /** @var stdClass $row */
                return StreamEvent::fromRow($row);
            })
            ->all());
    }

    /**
     * Fold a snapshot stream's events to one per subject. Its subscriber
     * needs the subject as it is, not every step on the way: ten price
     * changes in a minute are delivered as one, carrying every topic any of
     * them touched. Append events are never folded; each one happened.
     *
     * @param  list<StreamEvent>  $events
     * @return list<StreamEvent>
     */
    public function collapse(SubscriptionModel $subscription, array $events): array
    {
        if ($this->streams->get($subscription->stream)->kind() === StreamKind::Append) {
            return $events;
        }

        $latest = [];
        $topics = [];

        foreach ($events as $event) {
            $key = $event->subjectKey;
            // A removal resets what came before it: the subscriber is told to
            // forget the subject, then of anything after.
            $topics[$key] = $event->kind === EventKind::Removed ? $event->topics : ($topics[$key] ?? 0) | $event->topics;
            unset($latest[$key]);
            $latest[$key] = $event;
        }

        return array_values(array_map(
            fn (StreamEvent $event): StreamEvent => $event->withTopics($topics[$event->subjectKey]),
            $latest,
        ));
    }

    /**
     * The entries the subscriber receives for these events, in the
     * subscription's format.
     *
     * @param  list<StreamEvent>  $events
     * @return list<array<string, mixed>>
     */
    public function format(SubscriptionModel $subscription, array $events): array
    {
        if ($events === []) {
            return [];
        }

        $stream = $this->streams->get($subscription->stream);
        $formats = $stream->formats();
        $formatter = $formats[$subscription->format] ?? reset($formats);

        if ($formatter === false) {
            return [];
        }

        $snapshots = [];

        if ($stream->kind() === StreamKind::Snapshot) {
            $changed = array_values(array_unique(array_map(
                fn (StreamEvent $event): string => $event->subjectKey,
                array_filter($events, fn (StreamEvent $event): bool => $event->kind === EventKind::Changed),
            )));

            $snapshots = $changed === [] ? [] : $stream->snapshots($changed);
        }

        return $formatter->format($stream, $subscription, $events, $snapshots);
    }
}
