<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Data;

use RefactorCircus\Keystone\Data\Data;

/**
 * Input for ListSubscriptionEventsAction: where in the feed to read from and
 * how much.
 *
 * The feed keeps its own `after` and `limit` rather than Keystone's
 * Pagination: `after` is an event id the subscriber stores between reads,
 * and the subscriber feed API documents both names.
 */
final readonly class ListSubscriptionEventsData extends Data
{
    /**
     * @param  int  $after  The cursor a previous read returned; 0 to start.
     * @param  int  $limit  At most impex.subscriptions.feed.max_limit.
     */
    public function __construct(
        public int $after = 0,
        public int $limit = 100,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'after' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
