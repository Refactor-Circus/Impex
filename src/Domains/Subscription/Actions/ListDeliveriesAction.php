<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Validation\Rule;
use JayI\Impex\Domains\Subscription\Enums\DeliveryStatus;
use JayI\Impex\Domains\Subscription\Events\DeliveriesListedActionEvent;
use JayI\Impex\Domains\Subscription\Events\DeliveriesListingActionEvent;
use JayI\Impex\Domains\Subscription\Models\DeliveryModel;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

final class ListDeliveriesAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(DeliveryStatus::class)],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return CursorPaginator<int, DeliveryModel>
     */
    public function execute(SubscriptionModel $subscription, array $filters = []): CursorPaginator
    {
        DeliveriesListingActionEvent::dispatch($subscription);

        $query = DeliveryModel::query()
            ->where('subscription_id', $subscription->id)
            ->orderByDesc('attempted_at')
            ->orderByDesc('id');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $result = $query->cursorPaginate(
            perPage: isset($filters['per_page']) ? (int) $filters['per_page'] : 25,
            cursor: is_string($filters['cursor'] ?? null) ? $filters['cursor'] : null,
        );

        DeliveriesListedActionEvent::dispatch($result);

        return $result;
    }
}
