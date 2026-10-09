<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Channel\Actions\RotateChannelSecretAction;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Subscription\Exceptions\SubscriptionException;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;

final class RotateSubscriptionSecretRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return RotateChannelSecretAction::rules();
    }

    /**
     * A new signing secret for the subscription's endpoint. Deliveries carry
     * a signature under both until the next rotation, so the receiver can
     * switch at its own pace.
     */
    public function persist(): JsonResponse
    {
        $subscription = $this->subscription();
        $channel = $subscription->channel;

        if (! $channel instanceof ChannelModel) {
            throw SubscriptionException::notPushed($subscription->id);
        }

        $rotated = app(RotateChannelSecretAction::class)->execute($channel);

        return (new SubscriptionResource($subscription->load('channel')))
            ->additional(['secret' => $rotated['secret']])
            ->response();
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('update', $this->subscription());
    }
}
