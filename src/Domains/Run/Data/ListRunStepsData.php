<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Run\Enums\StepPhase;
use RefactorCircus\Keystone\Data\Data;

/**
 * Input for ListRunStepsAction: one phase, or both when left out.
 */
final readonly class ListRunStepsData extends Data
{
    public function __construct(
        public ?StepPhase $phase = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'phase' => ['sometimes', Rule::enum(StepPhase::class)],
        ];
    }
}
