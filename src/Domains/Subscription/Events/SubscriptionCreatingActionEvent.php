<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionStartingEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

/**
 * A subscription is about to be created.
 */
final class SubscriptionCreatingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public SubscriberModel $subscriber,
        public array $data,
    ) {}
}
