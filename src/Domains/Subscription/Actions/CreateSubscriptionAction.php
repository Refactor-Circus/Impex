<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Actions;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\Rule;
use JayI\Impex\Domains\Channel\Actions\CreateChannelAction;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use JayI\Impex\Domains\Subscription\Events\SubscriptionCreatedActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscriptionCreatingActionEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Domains\Subscription\Services\StreamRegistry;
use JayI\Impex\Domains\Subscription\Support\SubscriptionSettings;

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
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'stream' => ['required', 'string', 'max:191'],
            'topics' => ['sometimes', 'array'],
            'topics.*' => ['string', 'max:64'],
            'format' => ['sometimes', 'string', 'max:32'],
            'filter' => ['sometimes', 'nullable', 'array'],
            'subjects' => ['sometimes', 'array', 'max:10000'],
            'subjects.*' => ['string', 'max:191'],
            'options' => ['sometimes', 'array'],
            // Where to push. Leave it out for a feed-only subscription.
            'endpoint' => ['sometimes', 'nullable', 'array'],
            'endpoint.transport' => ['sometimes', Rule::in(['http', 'mail'])],
            'endpoint.url' => ['exclude_without:endpoint', 'required_without:endpoint.to', 'nullable', 'url', 'max:2048'],
            'endpoint.to' => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{subscription: SubscriptionModel, secret: string|null} The
     *                                                                     signing secret, returned this once.
     */
    public function execute(SubscriberModel $subscriber, array $data): array
    {
        SubscriptionCreatingActionEvent::dispatch($subscriber, $data);

        $stream = $this->streams->get((string) $data['stream']);
        $settings = $this->settings->resolve($stream, $data);

        /** @var array<string, mixed>|null $endpoint */
        $endpoint = $data['endpoint'] ?? null;
        $secret = $endpoint === null ? null : 'whsec_'.base64_encode(random_bytes(32));

        $subscription = $this->db->transaction(function () use ($subscriber, $stream, $settings, $data, $endpoint, $secret): SubscriptionModel {
            $subscription = SubscriptionModel::query()->create([
                'subscriber_id' => $subscriber->id,
                'stream' => $stream->key(),
                'status' => SubscriptionStatus::Active,
                'options' => $data['options'] ?? null,
                ...$settings,
            ]);

            if ($endpoint !== null) {
                $subscription->channel_id = $this->channel($subscriber, $subscription, $endpoint, (string) $secret)->id;
                $subscription->save();
            }

            /** @var list<string> $subjects */
            $subjects = $data['subjects'] ?? [];

            foreach (array_chunk(array_values(array_unique($subjects)), 1000) as $chunk) {
                $this->db->table('impex_subscription_subjects')->insertOrIgnore(array_map(
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

        return $this->channels->execute([
            'name' => 'subscription-'.$subscription->id,
            'direction' => Direction::Outbound->value,
            'transport' => $transport,
            'body_policy' => $this->config->get('impex.subscriptions.body_policy', 'failures'),
            'options' => array_filter([
                'url' => $endpoint['url'] ?? null,
                'to' => $endpoint['to'] ?? null,
                'gzip' => $this->config->get('impex.subscriptions.delivery.gzip', false) === true ? true : null,
            ], fn (mixed $value): bool => $value !== null),
            'credentials' => ['signing_secret' => $secret],
        ], $subscriber);
    }
}
