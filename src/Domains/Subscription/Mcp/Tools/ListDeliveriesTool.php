<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Subscription\Data\ListDeliveriesData;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\ListDeliveriesMcpRequest;
use RefactorCircus\Impex\Domains\Subscription\Resources\DeliveryResource;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('List a subscription\'s delivery attempts, newest first, with how each went.')]
final class ListDeliveriesTool extends Tool
{
    public function handle(ListDeliveriesMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscription' => $schema->string()->description('The subscription id.')->required(),
            'status' => $schema->string()->enum(['succeeded', 'failed']),
        ] + ListDeliveriesData::pagination()->schema($schema);
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return DeliveryResource::collectionSchema($schema, ListDeliveriesData::pagination());
    }
}
