<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Message\Data\ListMessagesData;
use RefactorCircus\Impex\Domains\Message\Events\MessagesListedActionEvent;
use RefactorCircus\Impex\Domains\Message\Events\MessagesListingActionEvent;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

final class ListMessagesAction
{
    /**
     * @param  Model|null  $viewer  When given, only messages of runs they own.
     * @return CursorPaginator<int, MessageModel>
     */
    public function execute(ListMessagesData $filters = new ListMessagesData, ?Model $viewer = null): CursorPaginator
    {
        MessagesListingActionEvent::dispatch($filters, $viewer);

        $result = $this->perform($filters, $viewer);

        MessagesListedActionEvent::dispatch($result, $viewer);

        return $result;
    }

    /**
     * @param  Model|null  $viewer  When given, only messages of runs they own.
     * @return CursorPaginator<int, MessageModel>
     */
    private function perform(ListMessagesData $filters, ?Model $viewer): CursorPaginator
    {
        $query = MessageModel::query()->latest('occurred_at');

        // A message with no run has no owner, so a viewer never sees it.
        if ($viewer !== null) {
            $query->whereHas('run', function (Builder $runs) use ($viewer): void {
                /** @var Builder<RunModel> $runs */
                $runs->whereOwnedBy($viewer);
            });
        }

        if ($filters->direction !== null) {
            $query->where('direction', $filters->direction);
        }

        if ($filters->channel !== null) {
            $query->where('channel', $filters->channel);
        }

        if ($filters->run !== null) {
            $query->where('run_id', $filters->run);
        }

        if ($filters->since !== null) {
            $query->where('occurred_at', '>=', $filters->since);
        }

        if ($filters->until !== null) {
            $query->where('occurred_at', '<=', $filters->until);
        }

        return $query->cursorPaginate(perPage: $filters->perPage, cursor: $filters->cursor);
    }
}
