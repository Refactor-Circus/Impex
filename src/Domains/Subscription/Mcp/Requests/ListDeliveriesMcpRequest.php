<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\ListDeliveriesAction;
use JayI\Impex\Domains\Subscription\Resources\DeliveryResource;
use Laravel\Mcp\ResponseFactory;

final class ListDeliveriesMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('view', $this->subscription());
    }

    protected function rules(): array
    {
        return ListDeliveriesAction::rules() + [
            'subscription' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        unset($validated['subscription']);

        $deliveries = app(ListDeliveriesAction::class)->execute($this->subscription(), $validated);

        return $this->structuredCollection(
            DeliveryResource::collection($deliveries->items())->resolve(),
            ['next_cursor' => $deliveries->nextCursor()?->encode()],
        );
    }
}
