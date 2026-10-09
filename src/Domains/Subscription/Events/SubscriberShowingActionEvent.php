<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionStartingEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;

/**
 * A subscriber is about to be shown.
 */
final class SubscriberShowingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public SubscriberModel $subscriber,
    ) {}
}
