<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\ListSubscribersMcpRequest;

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
            'cursor' => $schema->string()->description('The next_cursor of the previous page.'),
            'per_page' => $schema->integer()->min(1)->max(200),
        ];
    }
}
