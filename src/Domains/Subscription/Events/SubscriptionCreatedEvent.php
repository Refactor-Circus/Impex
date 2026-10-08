<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * The Subscription `created` Eloquent event.
 */
final class SubscriptionCreatedEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public SubscriptionModel $subscription) {}

    public function model(): Model
    {
        return $this->subscription;
    }

    public function hook(): string
    {
        return 'created';
    }
}
