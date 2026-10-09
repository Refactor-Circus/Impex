<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;

/**
 * A subscriber was changed.
 */
final class SubscriberUpdatedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public SubscriberModel $subscriber,
    ) {}
}
