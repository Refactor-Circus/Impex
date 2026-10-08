<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionFinishedEvent;

/**
 * A subscription was deleted.
 */
final class SubscriptionDeletedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $id,
    ) {}
}
