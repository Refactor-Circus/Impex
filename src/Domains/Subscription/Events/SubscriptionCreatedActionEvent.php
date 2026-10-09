<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * A subscription was created. Its signing secret is not carried.
 */
final class SubscriptionCreatedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public SubscriptionModel $subscription,
    ) {}
}
