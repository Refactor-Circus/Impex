<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The Subscriber `saving` Eloquent event.
 */
final class SubscriberSavingEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public SubscriberModel $subscriber) {}

    public function model(): Model
    {
        return $this->subscriber;
    }

    public function hook(): string
    {
        return 'saving';
    }
}
