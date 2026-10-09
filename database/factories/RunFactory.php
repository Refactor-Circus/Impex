<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

/**
 * @extends Factory<RunModel>
 */
final class RunFactory extends Factory
{
    protected $model = RunModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flow' => 'example',
            'flow_class' => 'App\\Flows\\ExampleFlow',
            'status' => RunStatus::Pending,
            'trigger' => RunTrigger::Code,
            'input' => ['value' => []],
        ];
    }
}
