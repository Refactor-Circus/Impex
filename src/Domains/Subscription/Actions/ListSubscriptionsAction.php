<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use JayI\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use JayI\Impex\Domains\Subscription\Events\SubscriptionsListedActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscriptionsListingActionEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

final class ListSubscriptionsAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'stream' => ['sometimes', 'string', 'max:191'],
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'subscriber' => ['sometimes', 'string', 'max:26'],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  SubscriberModel|null  $subscriber  Only this subscriber's.
     * @param  Model|null  $viewer  Only subscriptions of subscribers they own.
     * @return CursorPaginator<int, SubscriptionModel>
     */
    public function execute(array $filters = [], ?SubscriberModel $subscriber = null, ?Model $viewer = null): CursorPaginator
    {
        SubscriptionsListingActionEvent::dispatch($filters, $subscriber);

        $query = SubscriptionModel::query()->with('channel')->orderBy('id');

        if ($subscriber !== null) {
            $query->where('subscriber_id', $subscriber->id);
        }

        if ($viewer !== null) {
            $query->whereHas('subscriber', function (Builder $subscribers) use ($viewer): void {
                $subscribers->where('owner_type', $viewer->getMorphClass())->where('owner_id', (string) $viewer->getKey());
            });
        }

        foreach (['stream' => 'stream', 'status' => 'status', 'subscriber' => 'subscriber_id'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        $result = $query->cursorPaginate(
            perPage: isset($filters['per_page']) ? (int) $filters['per_page'] : 25,
            cursor: is_string($filters['cursor'] ?? null) ? $filters['cursor'] : null,
        );

        SubscriptionsListedActionEvent::dispatch($result);

        return $result;
    }
}
