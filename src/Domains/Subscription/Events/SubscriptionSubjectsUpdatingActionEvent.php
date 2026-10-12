<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Data\UpdateSubscriptionSubjectsData;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * A subscription's subject list is about to change.
 */
final class SubscriptionSubjectsUpdatingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public SubscriptionModel $subscription,
        public UpdateSubscriptionSubjectsData $data,
    ) {}
}
