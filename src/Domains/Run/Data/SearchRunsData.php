<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Data;

use RefactorCircus\Keystone\Data\Data;

/**
 * Input for SearchRunsAction: what to look for, and how many runs to return.
 */
final readonly class SearchRunsData extends Data
{
    /**
     * @param  string  $q  Matched against the flow name anywhere, and against the start of the run id.
     * @param  int  $limit  How many runs to return at most.
     */
    public function __construct(
        public string $q,
        public int $limit = 5,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ];
    }
}
