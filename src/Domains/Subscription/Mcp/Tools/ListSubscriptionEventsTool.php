<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\ListSubscriptionEventsMcpRequest;

#[Description('Read a subscription\'s feed after a cursor, in the shape its deliveries take. Pass the returned cursor back as after.')]
final class ListSubscriptionEventsTool extends Tool
{
    public function handle(ListSubscriptionEventsMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscription' => $schema->string()->description('The subscription id.')->required(),
            'after' => $schema->integer()->min(0),
            'limit' => $schema->integer()->min(1)->max(1000),
        ];
    }
}
