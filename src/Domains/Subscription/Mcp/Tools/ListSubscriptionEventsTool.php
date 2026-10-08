<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use JayI\Foundation\Mcp\Tool;
use JayI\Impex\Domains\Subscription\Mcp\Requests\ListSubscriptionEventsMcpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

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
