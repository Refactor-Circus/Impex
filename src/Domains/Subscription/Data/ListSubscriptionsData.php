<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Pagination\Pagination;

/**
 * Input for ListSubscriptionsAction: an optional stream, status and
 * subscriber, and the page.
 */
final readonly class ListSubscriptionsData extends Data
{
    public function __construct(
        public ?string $stream = null,
        public ?SubscriptionStatus $status = null,
        public ?string $subscriber = null,
        public int $perPage = 25,
        public ?string $cursor = null,
    ) {}

    /**
     * How the list pages; its tool states the same limits.
     */
    public static function pagination(): Pagination
    {
        return Pagination::cursor(default: 25, max: 200);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'stream' => ['sometimes', 'string', 'max:191'],
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'subscriber' => ['sometimes', 'string', 'max:26'],
        ] + self::pagination()->rules();
    }
}
