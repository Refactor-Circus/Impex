<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscriptionsData;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * Subscriptions are about to be listed.
 */
final class SubscriptionsListingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ListSubscriptionsData $filters,
        public ?SubscriberModel $subscriber = null,
    ) {}
}
