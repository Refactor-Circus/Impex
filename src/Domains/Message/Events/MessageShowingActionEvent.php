<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionStartingEvent;
use JayI\Impex\Domains\Message\Models\MessageModel;

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
