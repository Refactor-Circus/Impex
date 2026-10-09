<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Signal\Models\SignalModel;
use RefactorCircus\Impex\Support\Policies\Policy;

/**
 * Reading a run's signals needs `view` on the run; delivering one needs
 * `signal`. A delivered signal is history, so nothing updates or deletes it.
 */
class SignalPolicy extends Policy
{
    public function viewAny(Model $user, RunModel $run): bool
    {
        return $this->allowsOnRun($user, 'view', $run);
    }

    public function view(Model $user, SignalModel $signal): bool
    {
        return $this->allowsOnRun($user, 'view', $signal->run);
    }

    public function create(Model $user, RunModel $run): bool
    {
        return $this->allowsOnRun($user, 'signal', $run);
    }
}
