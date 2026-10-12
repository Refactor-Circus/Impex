<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscribersData;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\ListSubscribersMcpRequest;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriberResource;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('List subscribers: the vendors and partners who receive this application\'s changes.')]
final class ListSubscribersTool extends Tool
{
    public function handle(ListSubscribersMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['active', 'disabled']),
        ] + ListSubscribersData::pagination()->schema($schema);
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return SubscriberResource::collectionSchema($schema, ListSubscribersData::pagination());
    }
}
