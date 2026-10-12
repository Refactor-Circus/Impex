<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscribersData;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscribersListedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscribersListingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;

final class ListSubscribersAction
{
    /**
     * @param  Model|null  $viewer  When given, only the subscribers they own.
     * @return CursorPaginator<int, SubscriberModel>
     */
    public function execute(ListSubscribersData $filters = new ListSubscribersData, ?Model $viewer = null): CursorPaginator
    {
        SubscribersListingActionEvent::dispatch($filters, $viewer);

        $query = SubscriberModel::query()->orderBy('id');

        if ($viewer !== null) {
            $query->where('owner_type', $viewer->getMorphClass())->where('owner_id', (string) $viewer->getKey());
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        $result = $query->cursorPaginate(perPage: $filters->perPage, cursor: $filters->cursor);

        SubscribersListedActionEvent::dispatch($result);

        return $result;
    }
}
