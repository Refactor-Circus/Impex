<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Actions;

use JayI\Impex\Domains\Subscription\Events\SubscriptionShowingActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscriptionShownActionEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

final class ShowSubscriptionAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    public function execute(SubscriptionModel $subscription): SubscriptionModel
    {
        SubscriptionShowingActionEvent::dispatch($subscription);

        $subscription->loadMissing(['channel', 'subscriber']);

        SubscriptionShownActionEvent::dispatch($subscription);

        return $subscription;
    }
}
