<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Policies;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Support\Policies\Policy;

/**
 * A subscription follows its subscriber through the Gate, so whichever
 * subscriber policy is registered decides.
 */
class SubscriptionPolicy extends Policy
{
    public function viewAny(Model $user): bool
    {
        return true;
    }

    public function view(Model $user, SubscriptionModel $subscription): bool
    {
        return $this->allowsOn($user, 'view', $subscription->subscriber);
    }

    public function create(Model $user, SubscriberModel $subscriber): bool
    {
        return $this->allowsOn($user, 'update', $subscriber);
    }

    public function update(Model $user, SubscriptionModel $subscription): bool
    {
        return $this->allowsOn($user, 'update', $subscription->subscriber);
    }

    public function delete(Model $user, SubscriptionModel $subscription): bool
    {
        return $this->allowsOn($user, 'update', $subscription->subscriber);
    }
}
