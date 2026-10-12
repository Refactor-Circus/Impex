<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Run\Data\ListRunsData;
use RefactorCircus\Impex\Domains\Run\Events\RunsListedActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunsListingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

final class ListRunsAction
{
    /**
     * @param  Model|null  $viewer  When given, only the runs they own.
     * @return CursorPaginator<int, RunModel>
     */
    public function execute(ListRunsData $filters = new ListRunsData, ?Model $viewer = null): CursorPaginator
    {
        RunsListingActionEvent::dispatch($filters, $viewer);

        $result = $this->perform($filters, $viewer);

        RunsListedActionEvent::dispatch($result, $viewer);

        return $result;
    }

    /**
     * @param  Model|null  $viewer  When given, only the runs they own.
     * @return CursorPaginator<int, RunModel>
     */
    private function perform(ListRunsData $filters, ?Model $viewer): CursorPaginator
    {
        // Newest first, with the id breaking ties: runs started in the same
        // second would otherwise be skipped or repeated across cursor pages.
        $query = RunModel::query()->latest('created_at')->latest('id');

        if ($viewer !== null) {
            $query->whereOwnedBy($viewer);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->flow !== null) {
            $query->where('flow', $filters->flow);
        }

        if ($filters->trigger !== null) {
            $query->where('trigger', $filters->trigger);
        }

        if ($filters->ownerType !== null && $filters->ownerId !== null) {
            $query->whereHas('owners', function (Builder $owners) use ($filters): void {
                $owners->where('owner_type', $filters->ownerType)
                    ->where('owner_id', $filters->ownerId);
            });
        }

        if ($filters->parent !== null) {
            $query->where('parent_run_id', $filters->parent);
        }

        if ($filters->since !== null) {
            $query->where('created_at', '>=', $filters->since);
        }

        if ($filters->until !== null) {
            $query->where('created_at', '<=', $filters->until);
        }

        foreach ($filters->tag as $key => $value) {
            $query->where('tags->'.$key, $value);
        }

        // Cursor, not offset: a ledger only grows, and deep offset pagination
        // degrades badly once it does.
        return $query->cursorPaginate(perPage: $filters->perPage, cursor: $filters->cursor);
    }
}
