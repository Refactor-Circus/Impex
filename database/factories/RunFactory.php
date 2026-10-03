<?php

declare(strict_types=1);

namespace JayI\Impex\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\RunTrigger;
use JayI\Impex\Domains\Run\Models\RunModel;

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
