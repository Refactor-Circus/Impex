<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use RefactorCircus\Impex\Domains\Subscription\Data\ListDeliveriesData;
use RefactorCircus\Impex\Domains\Subscription\Events\DeliveriesListedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\DeliveriesListingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\DeliveryModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

final class ListDeliveriesAction
{
    /**
     * @return CursorPaginator<int, DeliveryModel>
     */
    public function execute(SubscriptionModel $subscription, ListDeliveriesData $filters = new ListDeliveriesData): CursorPaginator
    {
        DeliveriesListingActionEvent::dispatch($subscription);

        $query = DeliveryModel::query()
            ->where('subscription_id', $subscription->id)
            ->orderByDesc('attempted_at')
            ->orderByDesc('id');

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        $result = $query->cursorPaginate(perPage: $filters->perPage, cursor: $filters->cursor);

        DeliveriesListedActionEvent::dispatch($result);

        return $result;
    }
}
