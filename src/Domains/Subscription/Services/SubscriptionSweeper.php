<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use RefactorCircus\Impex\Support\Tables;

/**
 * The subscriptions' share of `impex:tick`: the safety net under the jobs
 * that touches and fan-outs queue themselves. It picks up detection a lost
 * job never ran, and deliveries whose backoff has elapsed.
 */
final class SubscriptionSweeper
{
    public function __construct(
        private readonly SubscriptionJobs $jobs,
        private readonly ConnectionInterface $db,
        private readonly StreamRegistry $streams,
    ) {}

    /**
     * @return array{detections: int, deliveries: int}
     */
    public function sweep(int $limit = 1000): array
    {
        $now = Carbon::now();

        // One indexed existence check per registered stream, rather than a
        // DISTINCT over every touch and every event on each tick.
        $streams = [];

        foreach (array_keys($this->streams->all()) as $stream) {
            $pending = $this->db->table(Tables::name('stream_touches'))
                ->where('stream', $stream)
                ->where(fn (Builder $q) => $q->whereNull('leased_until')->orWhere('leased_until', '<', $now))
                ->exists()
                || $this->db->table(Tables::name('events'))
                    ->where('stream', $stream)
                    ->whereNull('fanned_out_at')
                    ->exists();

            if ($pending) {
                $streams[] = (string) $stream;
            }
        }

        foreach ($streams as $stream) {
            $this->jobs->detect($stream);
        }

        $due = $this->db->table(Tables::name('subscriptions'))
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('channel_id')
            ->whereNotNull('pending_at')
            ->where(fn (Builder $q) => $q->whereNull('paused_until')->orWhere('paused_until', '<=', $now))
            ->orderBy('pending_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($due as $id) {
            $this->jobs->deliver((string) $id);
        }

        return ['detections' => count($streams), 'deliveries' => $due->count()];
    }
}
