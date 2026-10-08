<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Subscription\Models\DeliveryModel;

/**
 * The Delivery `saved` Eloquent event.
 */
final class DeliverySavedEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public DeliveryModel $delivery) {}

    public function model(): Model
    {
        return $this->delivery;
    }

    public function hook(): string
    {
        return 'saved';
    }
}
