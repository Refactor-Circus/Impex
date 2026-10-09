<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * Drops events older than `impex.retention.events_days`, with the rows that
 * routed them to subscribers.
 *
 * Events are deleted by id range rather than by date: ids rise with time, so
 * everything up to the newest expired id goes, a slice at a time, each slice
 * a short indexed delete instead of one long lock on a table with hundreds
 * of millions of rows.
 */
final class EventPruner
{
    private const int SLICE = 10000;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Config $config,
    ) {}

    public function prune(): int
    {
        /** @var int $days */
        $days = $this->config->get('impex.retention.events_days', 30);

        $upTo = $this->db->table('impex_events')->where('occurred_at', '<=', Carbon::now()->subDays($days))->max('id');
        $from = $this->db->table('impex_events')->min('id');

        if ($upTo === null || $from === null) {
            return 0;
        }

        $deleted = 0;

        for ($low = (int) $from; $low <= (int) $upTo; $low += self::SLICE) {
            $high = min((int) $upTo, $low + self::SLICE - 1);

            $this->db->table('impex_subscription_events')->whereBetween('event_id', [$low, $high])->delete();
            $deleted += $this->db->table('impex_events')->whereBetween('id', [$low, $high])->delete();
        }

        return $deleted;
    }
}
