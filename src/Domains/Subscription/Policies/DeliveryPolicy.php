<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Policies;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Domains\Subscription\Models\DeliveryModel;
use JayI\Impex\Support\Policies\Policy;

/**
 * Deliveries are read-only, and follow their subscription.
 */
class DeliveryPolicy extends Policy
{
    public function viewAny(Model $user): bool
    {
        return true;
    }

    public function view(Model $user, DeliveryModel $delivery): bool
    {
        return $this->allowsOn($user, 'view', $delivery->subscription);
    }
}
