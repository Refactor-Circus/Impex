<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Policies;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Support\Policies\Policy;

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
