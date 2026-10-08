<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use JayI\Impex\Domains\Channel\Actions\UpdateChannelAction;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use JayI\Impex\Domains\Subscription\Events\SubscriptionUpdatedActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscriptionUpdatingActionEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Domains\Subscription\Services\StreamRegistry;
use JayI\Impex\Domains\Subscription\Services\SubscriptionJobs;
use JayI\Impex\Domains\Subscription\Support\SubscriptionSettings;

final class UpdateSubscriptionAction
{
    public function __construct(
        private readonly StreamRegistry $streams,
        private readonly SubscriptionSettings $settings,
        private readonly UpdateChannelAction $channels,
        private readonly SubscriptionJobs $jobs,
    ) {}

    /**
     * The stream is fixed; a different stream is a different subscription.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'topics' => ['sometimes', 'array'],
            'topics.*' => ['string', 'max:64'],
            'format' => ['sometimes', 'string', 'max:32'],
            'filter' => ['sometimes', 'nullable', 'array'],
            'options' => ['sometimes', 'nullable', 'array'],
            // Active resumes a paused or disabled subscription; paused holds
            // its events until then.
            'status' => ['sometimes', Rule::in([SubscriptionStatus::Active->value, SubscriptionStatus::Paused->value])],
            // Deliver again from this event on.
            'replay_from' => ['sometimes', 'integer', 'min:1'],
            'endpoint' => ['sometimes', 'array'],
            'endpoint.url' => ['sometimes', 'url', 'max:2048'],
            'endpoint.to' => ['sometimes', 'string', 'max:1024'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(SubscriptionModel $subscription, array $data): SubscriptionModel
    {
        SubscriptionUpdatingActionEvent::dispatch($subscription, $data);

        $stream = $this->streams->get($subscription->stream);
        $subscription->fill($this->settings->resolve($stream, $data, creating: false));

        if (array_key_exists('options', $data)) {
            $subscription->options = is_array($data['options']) ? $data['options'] : null;
        }

        if (($data['status'] ?? null) === SubscriptionStatus::Active->value) {
            $subscription->fill(['status' => SubscriptionStatus::Active, 'failures' => 0, 'disabled_at' => null, 'paused_until' => null, 'pending_at' => Carbon::now()]);
        } elseif (($data['status'] ?? null) === SubscriptionStatus::Paused->value) {
            $subscription->status = SubscriptionStatus::Paused;
        }

        if (isset($data['replay_from'])) {
            $subscription->cursor = max(0, (int) $data['replay_from'] - 1);
            $subscription->pending_at = Carbon::now();
        }

        $subscription->save();

        /** @var array<string, mixed>|null $endpoint */
        $endpoint = $data['endpoint'] ?? null;
        $channel = $subscription->channel;

        if ($endpoint !== null && $channel instanceof ChannelModel) {
            $this->channels->execute($channel, ['options' => [...($channel->options ?? []), ...array_intersect_key($endpoint, array_flip(['url', 'to']))]]);
        }

        if ($subscription->status === SubscriptionStatus::Active && $subscription->pushes() && $subscription->pending_at !== null) {
            $this->jobs->deliver($subscription->id);
        }

        SubscriptionUpdatedActionEvent::dispatch($subscription);

        return $subscription->refresh()->load('channel');
    }
}
