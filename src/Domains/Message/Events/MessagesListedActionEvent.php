<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Events;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;

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
