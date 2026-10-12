<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Actions\UpdateSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Data\UpdateSubscriptionData;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;

final class UpdateSubscriptionRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return UpdateSubscriptionData::rules();
    }

    public function persist(): JsonResponse
    {
        $subscription = app(UpdateSubscriptionAction::class)->execute($this->subscription(), UpdateSubscriptionData::fromValidated($this->validated()));

        return (new SubscriptionResource($subscription))->response();
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('update', $this->subscription());
    }
}
