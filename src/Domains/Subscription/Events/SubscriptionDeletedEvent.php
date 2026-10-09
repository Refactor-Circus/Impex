<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ModelLifecycleEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * The Subscription `deleted` Eloquent event.
 */
final class SubscriptionDeletedEvent implements ModelLifecycleEvent
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
        return 'deleted';
    }
}
