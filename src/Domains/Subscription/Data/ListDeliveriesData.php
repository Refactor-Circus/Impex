<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Subscription\Enums\DeliveryStatus;
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Pagination\Pagination;

/**
 * Input for ListDeliveriesAction: an optional outcome, and the page.
 */
final readonly class ListDeliveriesData extends Data
{
    public function __construct(
        public ?DeliveryStatus $status = null,
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
            'status' => ['sometimes', Rule::enum(DeliveryStatus::class)],
        ] + self::pagination()->rules();
    }
}
