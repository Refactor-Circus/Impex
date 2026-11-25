<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Tests\Fixtures\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Run\Policies\RunPolicy;

/**
 * Nobody may list runs, so every run-level read is refused.
 */
final class RefusingRunPolicy extends RunPolicy
{
    public function viewAny(Model $user): bool
    {
        return false;
    }
}
