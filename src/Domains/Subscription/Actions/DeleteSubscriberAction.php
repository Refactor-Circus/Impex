<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Support\Facades\DB;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriberDeletedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriberDeletingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

final class DeleteSubscriberAction
{
    public function __construct(private readonly DeleteSubscriptionAction $subscriptions) {}

    public function execute(SubscriberModel $subscriber): void
    {
        SubscriberDeletingActionEvent::dispatch($subscriber);

        $id = $subscriber->id;

        DB::transaction(function () use ($subscriber): void {
            // Each subscription goes the long way, so its endpoint channel and
            // pending events go with it.
            $subscriber->subscriptions()->each(function (SubscriptionModel $subscription): void {
                $this->subscriptions->execute($subscription);
            });

            $subscriber->delete();
        });

        SubscriberDeletedActionEvent::dispatch($id);
    }
}
