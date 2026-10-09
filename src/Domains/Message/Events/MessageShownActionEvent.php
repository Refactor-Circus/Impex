<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;

/**
 * A ledger message was shown.
 */
final class MessageShownActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public MessageModel $message,
    ) {}
}
