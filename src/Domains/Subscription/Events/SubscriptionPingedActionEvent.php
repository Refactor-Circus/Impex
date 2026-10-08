<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Impex\Domains\Channel\Data\Receipt;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * A test delivery was sent.
 */
final class SubscriptionPingedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public SubscriptionModel $subscription,
        public Receipt $receipt,
    ) {}
}
