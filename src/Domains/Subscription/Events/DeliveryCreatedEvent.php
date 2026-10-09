<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Models\DeliveryModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The Delivery `created` Eloquent event.
 */
final class DeliveryCreatedEvent implements ModelLifecycleEvent
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
        return 'created';
    }
}
