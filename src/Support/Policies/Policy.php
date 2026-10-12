<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Support\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
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
        return $this->allowsOnParent($user, $ability, $run, $arguments);
    }

    /**
     * Ask the Gate about a parent model, so a child follows whichever policy
     * is registered for its parent. A record with no parent, or a user model
     * the Gate cannot act as, is refused.
     *
     * @param  array<int, mixed>  $arguments
     */
    protected function allowsOnParent(Model $user, string $ability, ?Model $parent, array $arguments = []): bool
    {
        return $parent instanceof Model
            && $user instanceof Authenticatable
            && $this->allowsOn($user, $ability, $parent, $arguments);
    }
}
