<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Pagination\Pagination;

/**
 * Input for ListRunsAction: which runs to show, and the page.
 */
final readonly class ListRunsData extends Data
{
    /**
     * @param  array<string, string>  $tag  Tag key/value pairs the run must carry.
     */
    public function __construct(
        public ?RunStatus $status = null,
        public ?string $flow = null,
        public ?RunTrigger $trigger = null,
        public ?string $ownerType = null,
        public ?string $ownerId = null,
        public array $tag = [],
        public ?string $since = null,
        public ?string $until = null,
        public ?string $parent = null,
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
            'status' => ['sometimes', Rule::enum(RunStatus::class)],
            'flow' => ['sometimes', 'string', 'max:191'],
            'trigger' => ['sometimes', Rule::enum(RunTrigger::class)],
            'owner_type' => ['sometimes', 'string', 'max:191', 'required_with:owner_id'],
            'owner_id' => ['sometimes', 'string', 'max:64', 'required_with:owner_type'],
            'tag' => ['sometimes', 'array'],
            'since' => ['sometimes', 'date'],
            'until' => ['sometimes', 'date'],
            'parent' => ['sometimes', 'string', 'max:26'],
        ] + self::pagination()->rules();
    }
}
