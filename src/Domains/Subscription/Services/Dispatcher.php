<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use JayI\Impex\Domains\Channel\Data\ChannelConfig;
use JayI\Impex\Domains\Channel\Data\OutboundMessage;
use JayI\Impex\Domains\Channel\Data\Receipt;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Channel\Services\ChannelRegistry;
use JayI\Impex\Domains\Channel\Services\ChannelSender;
use JayI\Impex\Domains\Subscription\Data\StreamEvent;
use JayI\Impex\Domains\Subscription\Enums\DeliveryStatus;
use JayI\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use JayI\Impex\Domains\Subscription\Events\SubscriptionDisabled;
use JayI\Impex\Domains\Subscription\Exceptions\SubscriptionException;
use JayI\Impex\Domains\Subscription\Mail\SubscriptionDigestMail;
use JayI\Impex\Domains\Subscription\Models\DeliveryModel;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Domains\Subscription\Support\Backoff;
use JayI\Impex\Support\Locks;

/**
 * Delivers a subscription's pending events, a batch per request, in order.
 *
 * One delivery runs per subscription at a time, so a subscriber never
 * receives batch two before batch one. Its cursor moves only once the other
 * side has accepted a batch; a failure backs off, and enough failures in a
 * row switch the subscription off rather than hammering a dead endpoint.
 */
final class Dispatcher
{
    public function __construct(
        private readonly EventReader $reader,
        private readonly ChannelRegistry $channels,
        private readonly ChannelSender $sender,
        private readonly Backoff $backoff,
        private readonly Locks $locks,
        private readonly SubscriptionJobs $jobs,
        private readonly ConnectionInterface $db,
        private readonly Config $config,
        private readonly Events $events,
    ) {}

    /**
     * Deliver until the subscription is caught up, it fails, or the time
     * budget runs out — then queue another pass if there is more.
     *
     * @return int The number of events delivered.
     */
    public function deliver(string $subscriptionId): int
    {
        /** @var int $budget */
        $budget = $this->config->get('impex.subscriptions.delivery.time_budget', 50);

        $lock = $this->locks->acquire('impex:subscription:'.$subscriptionId, $budget + 60);

        if (! $lock->get()) {
            return 0;
        }

        try {
            return $this->drain($subscriptionId, $budget);
        } finally {
            $lock->release();
        }
    }

    /**
     * What a subscriber receives: the events, and the cursor to resume from.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    public function envelope(SubscriptionModel $subscription, array $entries, string $type = 'events', ?int $cursor = null): array
    {
        return array_filter([
            'type' => $type,
            'subscription' => $subscription->id,
            'stream' => $subscription->stream,
            'events' => $entries,
            'cursor' => $cursor === null ? null : (string) $cursor,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Send a body to the subscription's channel, recorded against it.
     *
     * @param  array<string, mixed>  $envelope
     */
    public function send(SubscriptionModel $subscription, array $envelope, string $id, ?string $deliveryId = null): Receipt
    {
        $channel = $this->channel($subscription);
        $links = ['delivery_id' => $deliveryId];

        /** @var list<array<string, mixed>> $entries */
        $entries = $envelope['events'] ?? [];

        $message = $channel->transport === 'mail'
            ? OutboundMessage::mail(new SubscriptionDigestMail($subscription->stream, $entries), id: $id, links: $links)
            : OutboundMessage::json($envelope, id: $id, links: $links);

        return $this->sender->send($channel, $message);
    }

    private function drain(string $subscriptionId, int $budget): int
    {
        $started = Carbon::now();
        $deadline = microtime(true) + $budget;
        $delivered = 0;

        /** @var int $batch */
        $batch = $this->config->get('impex.subscriptions.delivery.batch', 100);

        while (true) {
            $subscription = SubscriptionModel::query()->find($subscriptionId);

            if (! $subscription instanceof SubscriptionModel || ! $this->deliverable($subscription)) {
                return $delivered;
            }

            $raw = $this->reader->read($subscription, $subscription->cursor, $batch);

            if ($raw === []) {
                // Caught up. Only a mark older than this pass is cleared: one
                // set by a fan-out that landed mid-pass still wants a pass.
                $this->db->table('impex_subscriptions')
                    ->where('id', $subscriptionId)
                    ->where('pending_at', '<=', $started)
                    ->update(['pending_at' => null]);

                return $delivered;
            }

            if (! $this->deliverBatch($subscription, $raw)) {
                return $delivered;
            }

            $delivered += count($raw);

            if (microtime(true) >= $deadline) {
                $this->jobs->deliver($subscriptionId);

                return $delivered;
            }
        }
    }

    /**
     * @param  non-empty-list<StreamEvent>  $raw
     */
    private function deliverBatch(SubscriptionModel $subscription, array $raw): bool
    {
        $first = $raw[0]->id;
        $last = $raw[count($raw) - 1]->id;
        $entries = $this->reader->format($subscription, $this->reader->collapse($subscription, $raw));
        $deliveryId = (new DeliveryModel)->newUniqueId();

        // The same range keeps the same id across retries, so a subscriber
        // that already processed it can tell a redelivery from new work.
        $receipt = $this->send(
            $subscription,
            $this->envelope($subscription, $entries, cursor: $last),
            sprintf('%s.%d-%d', $subscription->id, $first, $last),
            $deliveryId,
        );

        $now = Carbon::now();

        $this->db->table('impex_deliveries')->insert([
            'id' => $deliveryId,
            'subscription_id' => $subscription->id,
            'first_event_id' => $first,
            'last_event_id' => $last,
            'events' => count($raw),
            'status' => ($receipt->successful ? DeliveryStatus::Succeeded : DeliveryStatus::Failed)->value,
            'message_id' => $receipt->messageId,
            'status_code' => $receipt->statusCode,
            'duration_ms' => $receipt->durationMs,
            'error' => $receipt->error === null ? null : json_encode($receipt->error),
            'attempted_at' => $now,
        ]);

        if ($receipt->successful) {
            $this->db->table('impex_subscriptions')->where('id', $subscription->id)->update([
                'cursor' => $last,
                'failures' => 0,
                'paused_until' => null,
                'last_delivered_at' => $now,
                'last_error' => null,
            ]);

            return true;
        }

        $this->fail($subscription, $receipt);

        return false;
    }

    private function fail(SubscriptionModel $subscription, Receipt $receipt): void
    {
        $failures = $subscription->failures + 1;

        /** @var int $threshold */
        $threshold = $this->config->get('impex.subscriptions.delivery.breaker_threshold', 20);

        $disable = $failures >= $threshold;

        $this->db->table('impex_subscriptions')->where('id', $subscription->id)->update([
            'failures' => $failures,
            'paused_until' => Carbon::now()->addSeconds($this->backoff->seconds($failures)),
            'last_error' => json_encode($receipt->error),
            ...($disable ? ['status' => SubscriptionStatus::Disabled->value, 'disabled_at' => Carbon::now()] : []),
        ]);

        if ($disable) {
            $this->events->dispatch(new SubscriptionDisabled($subscription->id));
        }
    }

    private function deliverable(SubscriptionModel $subscription): bool
    {
        return $subscription->status === SubscriptionStatus::Active
            && $subscription->pushes()
            && ($subscription->paused_until === null || $subscription->paused_until->isPast());
    }

    private function channel(SubscriptionModel $subscription): ChannelConfig
    {
        $channel = $subscription->channel;

        return $channel instanceof ChannelModel
            ? $this->channels->find($channel->name) ?? ChannelConfig::fromModel($channel)
            : throw SubscriptionException::notPushed($subscription->id);
    }
}
