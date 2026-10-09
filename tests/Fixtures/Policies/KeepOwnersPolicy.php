<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Tests\Fixtures\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Impex\Domains\Run\Policies\RunOwnerPolicy;

/**
 * Owners may be attached but never detached.
 */
final class KeepOwnersPolicy extends RunOwnerPolicy
{
    public function delete(Model $user, RunOwnerModel $owner): bool
    {
        return false;
    }
}
