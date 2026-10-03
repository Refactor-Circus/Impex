<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Events;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Impex\Contracts\ActionFinishedEvent;
use JayI\Impex\Domains\Message\Models\MessageModel;

/**
 * The ledger was read.
 */
final class MessagesListedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  CursorPaginator<int, MessageModel>  $messages
     */
    public function __construct(
        public CursorPaginator $messages,
        public ?Model $viewer = null,
    ) {}
}
