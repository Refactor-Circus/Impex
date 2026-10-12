<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscriptionsData;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionsListedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionsListingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

final class ListSubscriptionsAction
{
    /**
     * @param  SubscriberModel|null  $subscriber  Only this subscriber's.
     * @param  Model|null  $viewer  Only subscriptions of subscribers they own.
     * @return CursorPaginator<int, SubscriptionModel>
     */
    public function execute(ListSubscriptionsData $filters = new ListSubscriptionsData, ?SubscriberModel $subscriber = null, ?Model $viewer = null): CursorPaginator
    {
        SubscriptionsListingActionEvent::dispatch($filters, $subscriber);

        $query = SubscriptionModel::query()->with(['channel', 'subscriber'])->orderBy('id');

        if ($subscriber !== null) {
            $query->where('subscriber_id', $subscriber->id);
        }

        if ($viewer !== null) {
            $query->whereHas('subscriber', function (Builder $subscribers) use ($viewer): void {
                $subscribers->where('owner_type', $viewer->getMorphClass())->where('owner_id', (string) $viewer->getKey());
            });
        }

        if ($filters->stream !== null) {
            $query->where('stream', $filters->stream);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->subscriber !== null) {
            $query->where('subscriber_id', $filters->subscriber);
        }

        $result = $query->cursorPaginate(perPage: $filters->perPage, cursor: $filters->cursor);

        SubscriptionsListedActionEvent::dispatch($result);

        return $result;
    }
}
