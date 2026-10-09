<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * A ledger message is about to be shown.
 */
final class MessageShowingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public MessageModel $message,
    ) {}
}
