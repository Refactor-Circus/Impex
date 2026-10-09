<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListDeliveriesAction;
use RefactorCircus\Impex\Domains\Subscription\Resources\DeliveryResource;

final class IndexDeliveriesRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return ListDeliveriesAction::rules();
    }

    public function persist(): JsonResponse
    {
        return DeliveryResource::collection(app(ListDeliveriesAction::class)->execute($this->subscription(), $this->validated()))->response();
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('view', $this->subscription());
    }
}
