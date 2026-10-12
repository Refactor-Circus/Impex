<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use RefactorCircus\Impex\Domains\Channel\Actions\CreateChannelAction;
use RefactorCircus\Impex\Domains\Channel\Data\CreateChannelData;
use RefactorCircus\Impex\Domains\Channel\Enums\BodyPolicy;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Subscription\Data\CreateSubscriptionData;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionCreatedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionCreatingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\StreamRegistry;
use RefactorCircus\Impex\Domains\Subscription\Support\SubscriptionSettings;
use RefactorCircus\Impex\Support\Tables;

final class CreateSubscriptionAction
{
    public function __construct(
        private readonly StreamRegistry $streams,
        private readonly CreateChannelAction $channels,
        private readonly SubscriptionSettings $settings,
        private readonly ConnectionInterface $db,
        private readonly Config $config,
    ) {}

    /**
     * @return array{subscription: SubscriptionModel, secret: string|null} The
     *                                                                     signing secret, returned this once.
     */
    public function execute(SubscriberModel $subscriber, CreateSubscriptionData $data): array
    {
        SubscriptionCreatingActionEvent::dispatch($subscriber, $data);

        $stream = $this->streams->get($data->stream);
        $settings = $this->settings->resolve($stream, $data->toArray());
        $endpoint = $data->endpoint;
        $secret = $endpoint === null ? null : 'whsec_'.base64_encode(random_bytes(32));

        $subscription = $this->db->transaction(function () use ($subscriber, $stream, $settings, $data, $endpoint, $secret): SubscriptionModel {
            $subscription = SubscriptionModel::query()->create([
                'subscriber_id' => $subscriber->id,
                'stream' => $stream->key(),
                'status' => SubscriptionStatus::Active,
                'options' => $data->options,
                ...$settings,
            ]);

            if ($endpoint !== null) {
                $subscription->channel_id = $this->channel($subscriber, $subscription, $endpoint, (string) $secret)->id;
                $subscription->save();
            }

            foreach (array_chunk(array_values(array_unique($data->subjects)), 1000) as $chunk) {
                $this->db->table(Tables::name('subscription_subjects'))->insertOrIgnore(array_map(
                    fn (string $key): array => ['subscription_id' => $subscription->id, 'subject_key' => $key],
                    $chunk,
                ));
            }

            return $subscription;
        });

        SubscriptionCreatedActionEvent::dispatch($subscription);

        return ['subscription' => $subscription->refresh()->load('channel'), 'secret' => $secret];
    }

    /**
     * The subscriber's own outbound channel: owned by them, so the endpoint
     * guard applies, and keeping only failed bodies by default — a busy
     * subscription sends far too much to keep it all.
     *
     * @param  array<string, mixed>  $endpoint
     */
    private function channel(SubscriberModel $subscriber, SubscriptionModel $subscription, array $endpoint, string $secret): ChannelModel
    {
        $transport = is_string($endpoint['transport'] ?? null) ? $endpoint['transport'] : 'http';

        /** @var BodyPolicy|string $policy */
        $policy = $this->config->get('impex.subscriptions.body_policy', 'failures');

        return $this->channels->execute(new CreateChannelData(
            name: 'subscription-'.$subscription->id,
            direction: Direction::Outbound,
            transport: $transport,
            bodyPolicy: $policy instanceof BodyPolicy ? $policy : BodyPolicy::from($policy),
            options: array_filter([
                'url' => $endpoint['url'] ?? null,
                'to' => $endpoint['to'] ?? null,
                'gzip' => $this->config->get('impex.subscriptions.delivery.gzip', false) === true ? true : null,
            ], fn (mixed $value): bool => $value !== null),
            credentials: ['signing_secret' => $secret],
        ), $subscriber);
    }
}
