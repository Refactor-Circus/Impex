<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use JayI\Foundation\Mcp\Tool;
use JayI\Impex\Domains\Subscription\Mcp\Requests\ListSubscriptionsMcpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

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
            'cursor' => $schema->string()->description('The next_cursor of the previous page.'),
            'per_page' => $schema->integer()->min(1)->max(200),
        ];
    }
}
