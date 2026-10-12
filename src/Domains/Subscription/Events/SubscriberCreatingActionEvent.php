<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Data\CreateSubscriberData;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * A subscriber is about to be created.
 */
final class SubscriberCreatingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public CreateSubscriberData $data,
        public ?Model $owner = null,
    ) {}
}
