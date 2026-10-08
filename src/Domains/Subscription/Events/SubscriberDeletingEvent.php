<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

/**
 * The Subscriber `deleting` Eloquent event.
 */
final class SubscriberDeletingEvent implements ModelLifecycleEvent
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
        return 'deleting';
    }
}
