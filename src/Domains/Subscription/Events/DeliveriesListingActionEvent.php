<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionStartingEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * A subscription's deliveries are about to be listed.
 */
final class DeliveriesListingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public SubscriptionModel $subscription,
    ) {}
}
