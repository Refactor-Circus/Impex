<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Events;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

/**
 * Subscribers were listed.
 */
final class SubscribersListedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  CursorPaginator<int, SubscriberModel>  $subscribers
     */
    public function __construct(
        public CursorPaginator $subscribers,
    ) {}
}
