<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Models\DeliveryModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The Delivery `creating` Eloquent event.
 */
final class DeliveryCreatingEvent implements ModelLifecycleEvent
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
        return 'creating';
    }
}
