<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Data\UpdateSubscriberData;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * A subscriber is about to be changed.
 */
final class SubscriberUpdatingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public SubscriberModel $subscriber,
        public UpdateSubscriberData $data,
    ) {}
}
