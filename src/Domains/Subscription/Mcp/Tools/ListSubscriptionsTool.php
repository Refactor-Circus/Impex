<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscriptionsData;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\ListSubscriptionsMcpRequest;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('List subscriptions, optionally by stream, status or subscriber.')]
final class ListSubscriptionsTool extends Tool
{
    public function handle(ListSubscriptionsMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string(),
            'status' => $schema->string()->enum(['active', 'paused', 'disabled']),
            'subscriber' => $schema->string(),
        ] + ListSubscriptionsData::pagination()->schema($schema);
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return SubscriptionResource::collectionSchema($schema, ListSubscriptionsData::pagination());
    }
}
