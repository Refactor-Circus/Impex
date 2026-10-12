<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RefactorCircus\Impex\Domains\Channel\Actions\UpdateChannelAction;
use RefactorCircus\Impex\Domains\Channel\Data\UpdateChannelData;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Subscription\Data\UpdateSubscriptionData;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionUpdatedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionUpdatingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\Holdings;
use RefactorCircus\Impex\Domains\Subscription\Services\StreamRegistry;
use RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionJobs;
use RefactorCircus\Impex\Domains\Subscription\Support\SubscriptionSettings;
use RefactorCircus\Keystone\Data\Missing;

final class UpdateSubscriptionAction
{
    public function __construct(
        private readonly StreamRegistry $streams,
        private readonly SubscriptionSettings $settings,
        private readonly UpdateChannelAction $channels,
        private readonly SubscriptionJobs $jobs,
        private readonly Holdings $holdings,
    ) {}

    public function execute(SubscriptionModel $subscription, UpdateSubscriptionData $data): SubscriptionModel
    {
        SubscriptionUpdatingActionEvent::dispatch($subscription, $data);

        $stream = $this->streams->get($subscription->stream);

        DB::transaction(function () use ($subscription, $data, $stream): void {
            $selection = $subscription->selection;

            $subscription->fill($this->settings->resolve($stream, $data->toArray(), creating: false));

            if (! $data->options instanceof Missing) {
                $subscription->options = $data->options;
            }

            if ($data->status === SubscriptionStatus::Active) {
                $subscription->fill(['status' => SubscriptionStatus::Active, 'failures' => 0, 'disabled_at' => null, 'paused_until' => null, 'pending_at' => Carbon::now()]);
            } elseif ($data->status === SubscriptionStatus::Paused) {
                $subscription->status = SubscriptionStatus::Paused;
            }

            if (is_int($data->replayFrom)) {
                $subscription->cursor = max(0, $data->replayFrom - 1);
                $subscription->pending_at = Carbon::now();
            }

            $subscription->save();

            $this->holdings->selectionChanged($subscription->id, $selection, $subscription->selection);

            $channel = $subscription->channel;

            if (is_array($data->endpoint) && $channel instanceof ChannelModel) {
                $this->channels->execute($channel, new UpdateChannelData(
                    options: [...($channel->options ?? []), ...array_intersect_key($data->endpoint, array_flip(['url', 'to']))],
                ));
            }
        });

        if ($subscription->status === SubscriptionStatus::Active && $subscription->pushes() && $subscription->pending_at !== null) {
            $this->jobs->deliver($subscription->id);
        }

        SubscriptionUpdatedActionEvent::dispatch($subscription);

        return $subscription->refresh()->load('channel');
    }
}
