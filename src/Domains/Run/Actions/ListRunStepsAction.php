<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use JayI\Impex\Domains\Run\Enums\StepPhase;
use JayI\Impex\Domains\Run\Events\RunStepsListedActionEvent;
use JayI\Impex\Domains\Run\Events\RunStepsListingActionEvent;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;

final class ListRunStepsAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'phase' => ['sometimes', Rule::enum(StepPhase::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, RunStepModel>
     */
    public function execute(RunModel $run, array $filters = []): Collection
    {
        RunStepsListingActionEvent::dispatch($run, $filters);

        $result = $this->perform($run, $filters);

        RunStepsListedActionEvent::dispatch($run, $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, RunStepModel>
     */
    private function perform(RunModel $run, array $filters = []): Collection
    {
        return $run->steps()
            ->when(isset($filters['phase']), fn (Builder $query) => $query->where('phase', $filters['phase']))
            ->orderBy('phase')
            ->orderBy('sequence')
            ->get();
    }
}
