<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Services;

use Illuminate\Database\ConnectionInterface;
use RefactorCircus\Impex\Domains\Subscription\Enums\EventKind;
use RefactorCircus\Impex\Domains\Subscription\Enums\Selection;
use RefactorCircus\Impex\Support\Tables;
use stdClass;

/**
 * Which subjects each filtered or listed subscription currently has: those it
 * was sent whose latest word was not "removed".
 *
 * Kept as one narrow row per subscription and subject, written as fan-out
 * routes events, so working out who a subject reached or left reads only the
 * keys in hand rather than their whole event history. A subscription that
 * takes everything never enters or leaves scope and has no rows here.
 */
final class Holdings
{
    private const int SLICE = 1000;

    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * Which of these subscriptions hold each of these keys.
     *
     * @param  list<string>  $keys
     * @param  list<string>  $subscriptions
     * @return array<string, array<string, true>> Subscription ids, by key.
     */
    public function of(array $keys, array $subscriptions): array
    {
        $held = [];

        foreach (array_chunk($keys, 500) as $chunk) {
            foreach (array_chunk($subscriptions, 500) as $ids) {
                $rows = $this->db->table(Tables::name('subscription_holdings'))
                    ->whereIn('subscription_id', $ids)
                    ->whereIn('subject_key', $chunk)
                    ->get(['subscription_id', 'subject_key']);

                foreach ($rows as $row) {
                    /** @var stdClass $row */
                    $held[(string) $row->subject_key][(string) $row->subscription_id] = true;
                }
            }
        }

        return $held;
    }

    /**
     * Record what a fan-out sent: subjects now held, and subjects let go.
     *
     * @param  array<string, list<string>>  $held  Keys, by subscription id.
     * @param  array<string, list<string>>  $released  Keys, by subscription id.
     */
    public function apply(array $held, array $released): void
    {
        $rows = [];

        foreach ($held as $id => $keys) {
            foreach ($keys as $key) {
                $rows[] = ['subscription_id' => (string) $id, 'subject_key' => $key];
            }
        }

        foreach (array_chunk($rows, self::SLICE) as $chunk) {
            $this->db->table(Tables::name('subscription_holdings'))->insertOrIgnore($chunk);
        }

        foreach ($released as $id => $keys) {
            foreach (array_chunk($keys, self::SLICE) as $chunk) {
                $this->db->table(Tables::name('subscription_holdings'))
                    ->where('subscription_id', (string) $id)
                    ->whereIn('subject_key', $chunk)
                    ->delete();
            }
        }
    }

    /**
     * Keep the holdings right across a change of selection. One that took
     * everything had none kept, so it starts from its history; one that now
     * takes everything needs none.
     */
    public function selectionChanged(string $subscriptionId, Selection $from, Selection $to): void
    {
        if ($from === $to) {
            return;
        }

        if ($to === Selection::All) {
            $this->forget($subscriptionId);
        } elseif ($from === Selection::All) {
            $this->rebuild($subscriptionId);
        }
    }

    /**
     * Drop everything a subscription holds.
     */
    public function forget(string $subscriptionId): void
    {
        $this->db->table(Tables::name('subscription_holdings'))->where('subscription_id', $subscriptionId)->delete();
    }

    /**
     * Rebuild a subscription's holdings from the events it was sent, oldest
     * first, so each subject ends on its latest word. Used when holdings
     * start being kept for a subscription that has a history: on upgrade,
     * and when one that took everything becomes filtered or listed. Reads
     * the history once, a slice at a time, and covers the retention window.
     */
    public function rebuild(string $subscriptionId): void
    {
        $this->forget($subscriptionId);

        $after = 0;

        do {
            $eventIds = $this->db->table(Tables::name('subscription_events'))
                ->where('subscription_id', $subscriptionId)
                ->where('event_id', '>', $after)
                ->orderBy('event_id')
                ->limit(self::SLICE)
                ->pluck('event_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            if ($eventIds === []) {
                return;
            }

            $after = max($eventIds);
            $latest = [];

            foreach ($this->db->table(Tables::name('events'))
                ->whereIn('id', $eventIds)
                ->whereIn('kind', [EventKind::Changed->value, EventKind::Removed->value])
                ->orderBy('id')
                ->get(['subject_key', 'kind']) as $event) {
                /** @var stdClass $event */
                $latest[(string) $event->subject_key] = (string) $event->kind;
            }

            $held = array_keys(array_filter($latest, fn (string $kind): bool => $kind !== EventKind::Removed->value));
            $released = array_keys(array_filter($latest, fn (string $kind): bool => $kind === EventKind::Removed->value));

            $this->apply(
                [$subscriptionId => array_map('strval', $held)],
                [$subscriptionId => array_map('strval', $released)],
            );
        } while (count($eventIds) === self::SLICE);
    }
}
