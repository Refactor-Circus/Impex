<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Actions\CreateSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;

final class StoreSubscriptionRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return CreateSubscriptionAction::rules();
    }

    /**
     * The signing secret is in this response and no other.
     */
    public function persist(): JsonResponse
    {
        $created = app(CreateSubscriptionAction::class)->execute($this->owner(), $this->validated());

        return (new SubscriptionResource($created['subscription']))
            ->additional(['secret' => $created['secret']])
            ->response()
            ->setStatusCode(201);
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('create', SubscriptionModel::class, [$this->owner()]);
    }

    /**
     * The subscriber the subscription is for: the caller on the subscriber
     * API, the route's subscriber on the operator API.
     */
    private function owner(): SubscriberModel
    {
        $subscriber = $this->subscriber() ?? $this->route('subscriber');

        return $subscriber instanceof SubscriberModel ? $subscriber : abort(404);
    }
}
