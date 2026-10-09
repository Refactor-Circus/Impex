<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;

/**
 * A subscription's subject list changed.
 */
final class SubscriptionSubjectsUpdatedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array{added: int, removed: int}  $counts
     */
    public function __construct(
        public SubscriptionModel $subscription,
        public array $counts,
    ) {}
}
