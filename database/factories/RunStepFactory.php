<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RefactorCircus\Impex\Domains\Run\Enums\StepPhase;
use RefactorCircus\Impex\Domains\Run\Enums\StepStatus;
use RefactorCircus\Impex\Domains\Run\Enums\StepType;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunStepModel;

/**
 * @extends Factory<RunStepModel>
 */
final class RunStepFactory extends Factory
{
    protected $model = RunStepModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => RunModel::factory(),
            'phase' => StepPhase::Forward,
            'sequence' => 0,
            'type' => StepType::Action,
            'name' => 'App\\Flows\\Actions\\Example',
            'status' => StepStatus::Pending,
            'attempts' => 0,
            'max_attempts' => 1,
        ];
    }
}
