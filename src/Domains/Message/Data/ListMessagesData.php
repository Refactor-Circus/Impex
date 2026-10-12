<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Pagination\Pagination;

/**
 * Input for ListMessagesAction: which ledger rows to show, and the page.
 */
final readonly class ListMessagesData extends Data
{
    public function __construct(
        public ?Direction $direction = null,
        public ?string $channel = null,
        public ?string $run = null,
        public ?string $since = null,
        public ?string $until = null,
        public int $perPage = 25,
        public ?string $cursor = null,
    ) {}

    /**
     * Cursor, not offset: a ledger only grows, and deep offset pagination
     * degrades badly once it does. Its tool states the same limits.
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
            'direction' => ['sometimes', Rule::enum(Direction::class)],
            'channel' => ['sometimes', 'string', 'max:191'],
            'run' => ['sometimes', 'string', 'max:26'],
            'since' => ['sometimes', 'date'],
            'until' => ['sometimes', 'date'],
        ] + self::pagination()->rules();
    }
}
