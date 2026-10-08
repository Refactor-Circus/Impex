<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use JayI\Impex\Domains\Subscription\Enums\SubscriberStatus;
use JayI\Impex\Domains\Subscription\Events\SubscribersListedActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscribersListingActionEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

final class ListSubscribersAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(SubscriberStatus::class)],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Model|null  $viewer  When given, only the subscribers they own.
     * @return CursorPaginator<int, SubscriberModel>
     */
    public function execute(array $filters = [], ?Model $viewer = null): CursorPaginator
    {
        SubscribersListingActionEvent::dispatch($filters, $viewer);

        $query = SubscriberModel::query()->orderBy('id');

        if ($viewer !== null) {
            $query->where('owner_type', $viewer->getMorphClass())->where('owner_id', (string) $viewer->getKey());
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $result = $query->cursorPaginate(
            perPage: isset($filters['per_page']) ? (int) $filters['per_page'] : 25,
            cursor: is_string($filters['cursor'] ?? null) ? $filters['cursor'] : null,
        );

        SubscribersListedActionEvent::dispatch($result);

        return $result;
    }
}
