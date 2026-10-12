<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Data;

use RefactorCircus\Keystone\Data\Data;

/**
 * Input for CancelRunAction: why the run was cancelled, recorded on it.
 */
final readonly class CancelRunData extends Data
{
    public function __construct(
        public ?string $reason = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
