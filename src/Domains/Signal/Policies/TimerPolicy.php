<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Signal\Models\TimerModel;
use RefactorCircus\Impex\Support\Policies\Policy;

/**
 * Timers are engine state, so reading them needs `view` on the run and no
 * ability changes them.
 */
class TimerPolicy extends Policy
{
    public function viewAny(Model $user, RunModel $run): bool
    {
        return $this->allowsOnRun($user, 'view', $run);
    }

    public function view(Model $user, TimerModel $timer): bool
    {
        return $this->allowsOnRun($user, 'view', $timer->run);
    }
}
