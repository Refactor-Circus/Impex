<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Policies;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;
use JayI\Impex\Support\Policies\Policy;

/**
 * A subscriber belongs to whoever created it on the operator API — the user
 * looking after that vendor. Subscribers themselves never reach this policy:
 * their own API authenticates them as the subscriber.
 */
class SubscriberPolicy extends Policy
{
    public function viewAny(Model $user): bool
    {
        return true;
    }

    public function view(Model $user, SubscriberModel $subscriber): bool
    {
        return $subscriber->isOwnedBy($user);
    }

    public function create(Model $user): bool
    {
        return true;
    }

    public function update(Model $user, SubscriberModel $subscriber): bool
    {
        return $subscriber->isOwnedBy($user);
    }

    public function delete(Model $user, SubscriberModel $subscriber): bool
    {
        return $subscriber->isOwnedBy($user);
    }
}
