<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Database\ConnectionInterface;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionDeletedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionDeletingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Support\Tables;

final class DeleteSubscriptionAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * Deletes the subscription, its pending events, and its endpoint channel
     * when the subscriber owns it. The ledger keeps what was sent.
     */
    public function execute(SubscriptionModel $subscription): void
    {
        SubscriptionDeletingActionEvent::dispatch($subscription);

        $id = $subscription->id;

        $this->db->transaction(function () use ($subscription): void {
            $this->db->table(Tables::name('subscription_events'))->where('subscription_id', $subscription->id)->delete();
            $this->db->table(Tables::name('subscription_holdings'))->where('subscription_id', $subscription->id)->delete();

            $channel = $subscription->channel;
            $subscription->delete();

            if ($channel instanceof ChannelModel && $channel->owner_type === (new SubscriberModel)->getMorphClass()) {
                $channel->delete();
            }
        });

        SubscriptionDeletedActionEvent::dispatch($id);
    }
}
