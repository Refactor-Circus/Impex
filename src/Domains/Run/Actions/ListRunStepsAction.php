<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use RefactorCircus\Impex\Domains\Run\Data\ListRunStepsData;
use RefactorCircus\Impex\Domains\Run\Events\RunStepsListedActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunStepsListingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunStepModel;

final class ListRunStepsAction
{
    /**
     * @return Collection<int, RunStepModel>
     */
    public function execute(RunModel $run, ListRunStepsData $filters = new ListRunStepsData): Collection
    {
        RunStepsListingActionEvent::dispatch($run, $filters);

        $result = $this->perform($run, $filters);

        RunStepsListedActionEvent::dispatch($run, $result);

        return $result;
    }

    /**
     * @return Collection<int, RunStepModel>
     */
    private function perform(RunModel $run, ListRunStepsData $filters): Collection
    {
        return $run->steps()
            ->when($filters->phase !== null, fn (Builder $query) => $query->where('phase', $filters->phase))
            ->orderBy('phase')
            ->orderBy('sequence')
            ->get();
    }
}
