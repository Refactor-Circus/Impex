<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Support\Policies\Policy;

/**
 * Flow overrides are application-wide settings with no owner. Anyone signed
 * in may read the catalogue they shape; changing one — pausing a flow or
 * rescheduling it — is denied by default. An application that lets someone
 * change them points `impex.policies` at its own policy.
 */
class FlowOverridePolicy extends Policy
{
    public function viewAny(Model $user): bool
    {
        return true;
    }

    public function view(Model $user): bool
    {
        return true;
    }
}
