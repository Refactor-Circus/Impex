<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Message\Data\ListMessagesData;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * The ledger is about to be read.
 */
final class MessagesListingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ListMessagesData $filters,
        public ?Model $viewer = null,
    ) {}
}
