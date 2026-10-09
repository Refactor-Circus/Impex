<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListSubscriptionsAction;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;

final class IndexSubscriptionsRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return ListSubscriptionsAction::rules();
    }

    public function persist(): JsonResponse
    {
        $subscriptions = app(ListSubscriptionsAction::class)->execute(
            $this->validated(),
            $this->subscriber(),
            $this->subscriber() === null ? $this->actor() : null,
        );

        return SubscriptionResource::collection($subscriptions)->response();
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('viewAny', SubscriptionModel::class);
    }
}
