<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Actions;

use Illuminate\Database\ConnectionInterface;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Subscription\Events\SubscriptionDeletedActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscriptionDeletingActionEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

final class DeleteSubscriptionAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * Deletes the subscription, its pending events, and its endpoint channel
     * when the subscriber owns it. The ledger keeps what was sent.
     */
    public function execute(SubscriptionModel $subscription): void
    {
        SubscriptionDeletingActionEvent::dispatch($subscription);

        $id = $subscription->id;

        $this->db->transaction(function () use ($subscription): void {
            $this->db->table('impex_subscription_events')->where('subscription_id', $subscription->id)->delete();

            $channel = $subscription->channel;
            $subscription->delete();

            if ($channel instanceof ChannelModel && $channel->owner_type === (new SubscriberModel)->getMorphClass()) {
                $channel->delete();
            }
        });

        SubscriptionDeletedActionEvent::dispatch($id);
    }
}
