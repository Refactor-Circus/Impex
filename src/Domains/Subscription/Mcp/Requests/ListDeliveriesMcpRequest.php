<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListDeliveriesAction;
use RefactorCircus\Impex\Domains\Subscription\Data\ListDeliveriesData;
use RefactorCircus\Impex\Domains\Subscription\Resources\DeliveryResource;

final class ListDeliveriesMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('view', $this->subscription());
    }

    protected function rules(): array
    {
        return [
            'subscription' => ['required', 'string', 'max:26'],
        ] + ListDeliveriesData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $deliveries = app(ListDeliveriesAction::class)->execute($this->subscription(), ListDeliveriesData::fromValidated($validated));

        return DeliveryResource::collection($deliveries)->toStructuredResponse();
    }
}
