<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunStepModel;
use RefactorCircus\Impex\Support\Policies\Policy;

/**
 * Steps are a run's history, written only by the engine, so reading them
 * needs `view` on the run and no ability changes them: the Gate denies
 * create, update and delete.
 */
class RunStepPolicy extends Policy
{
    public function viewAny(Model $user, RunModel $run): bool
    {
        return $this->allowsOnRun($user, 'view', $run);
    }

    public function view(Model $user, RunStepModel $step): bool
    {
        return $this->allowsOnRun($user, 'view', $step->run);
    }
}
