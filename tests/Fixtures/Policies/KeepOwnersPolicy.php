<?php

declare(strict_types=1);

namespace JayI\Impex\Tests\Fixtures\Policies;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;
use JayI\Impex\Domains\Run\Policies\RunOwnerPolicy;

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
