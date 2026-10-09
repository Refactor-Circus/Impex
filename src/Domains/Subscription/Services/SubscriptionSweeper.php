<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;

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
    ) {}

    /**
     * @return array{detections: int, deliveries: int}
     */
    public function sweep(int $limit = 1000): array
    {
        $now = Carbon::now();

        $touched = $this->db->table('impex_stream_touches')
            ->where(fn (Builder $q) => $q->whereNull('leased_until')->orWhere('leased_until', '<', $now))
            ->distinct()
            ->pluck('stream');

        $appended = $this->db->table('impex_events')
            ->whereNull('fanned_out_at')
            ->distinct()
            ->pluck('stream');

        $streams = $touched->merge($appended)->map(fn (mixed $s): string => (string) $s)->unique()->values();

        foreach ($streams as $stream) {
            $this->jobs->detect($stream);
        }

        $due = $this->db->table('impex_subscriptions')
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

        return ['detections' => $streams->count(), 'deliveries' => $due->count()];
    }
}
