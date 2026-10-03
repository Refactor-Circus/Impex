<?php

declare(strict_types=1);

namespace JayI\Impex\Tests\Fixtures\Policies;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Policies\RunPolicy;

/**
 * Owners may read a run but never cancel it or change who owns it.
 */
final class ReadOnlyRunPolicy extends RunPolicy
{
    public function cancel(Model $user, RunModel $run): bool
    {
        return false;
    }

    public function share(Model $user, RunModel $run): bool
    {
        return false;
    }
}
