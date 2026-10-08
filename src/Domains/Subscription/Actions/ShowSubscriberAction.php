<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Actions;

use JayI\Impex\Domains\Subscription\Events\SubscriberShowingActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscriberShownActionEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

final class ShowSubscriberAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    public function execute(SubscriberModel $subscriber): SubscriberModel
    {
        SubscriberShowingActionEvent::dispatch($subscriber);

        $subscriber->loadCount('subscriptions');

        SubscriberShownActionEvent::dispatch($subscriber);

        return $subscriber;
    }
}
