<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscribersData;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * Subscribers are about to be listed.
 */
final class SubscribersListingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ListSubscribersData $filters,
        public ?Model $viewer = null,
    ) {}
}
