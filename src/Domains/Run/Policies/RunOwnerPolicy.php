<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Impex\Support\Policies\Policy;

/**
 * Seeing who owns a run needs `view` on the run; attaching or detaching an
 * owner, which decides who else may see it, needs `share`.
 */
class RunOwnerPolicy extends Policy
{
    public function viewAny(Model $user, RunModel $run): bool
    {
        return $this->allowsOnRun($user, 'view', $run);
    }

    public function view(Model $user, RunOwnerModel $owner): bool
    {
        return $this->allowsOnRun($user, 'view', $owner->run);
    }

    public function create(Model $user, RunModel $run): bool
    {
        return $this->allowsOnRun($user, 'share', $run);
    }

    public function update(Model $user, RunOwnerModel $owner): bool
    {
        return $this->allowsOnRun($user, 'share', $owner->run);
    }

    public function delete(Model $user, RunOwnerModel $owner): bool
    {
        return $this->allowsOnRun($user, 'share', $owner->run);
    }
}
