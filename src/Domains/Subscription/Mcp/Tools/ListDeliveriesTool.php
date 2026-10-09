<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\ListDeliveriesMcpRequest;

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
            'cursor' => $schema->string()->description('The next_cursor of the previous page.'),
            'per_page' => $schema->integer()->min(1)->max(200),
        ];
    }
}
