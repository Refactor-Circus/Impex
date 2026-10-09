<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Support\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Keystone\Policies\Policy as BasePolicy;

/**
 * Shared checks for the bundled policies, on top of the suite's base policy.
 *
 * Each policy is registered from `impex.policies`, so an application swaps
 * one by pointing its model at another class there.
 */
abstract class Policy extends BasePolicy
{
    /**
     * Whether the user is one of the run's owners, in any role.
     */
    protected function owns(Model $user, RunModel $run): bool
    {
        $type = $user->getMorphClass();
        $id = (string) $user->getKey();

        if ($run->relationLoaded('owners')) {
            return $run->owners->contains(
                fn (RunOwnerModel $owner): bool => $owner->owner_type === $type && $owner->owner_id === $id,
            );
        }

        return $run->owners()->where('owner_type', $type)->where('owner_id', $id)->exists();
    }

    /**
     * Ask the Gate about the parent run, so a model that belongs to a run
     * follows whichever run policy is registered. A record with no run —
     * a channel message not yet bound to one, say — has no owner to ask.
     *
     * @param  array<int, mixed>  $arguments
     */
    protected function allowsOnRun(Model $user, string $ability, ?RunModel $run, array $arguments = []): bool
    {
        return $run instanceof RunModel && $this->allowsOn($user, $ability, $run, $arguments);
    }
}
