<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use JayI\Foundation\Mcp\Tool;
use JayI\Impex\Domains\Subscription\Mcp\Requests\ShowSubscriptionMcpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show one subscription: its topics, filter, endpoint, cursor and delivery health. Never its secret.')]
final class ShowSubscriptionTool extends Tool
{
    public function handle(ShowSubscriptionMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscription' => $schema->string()->description('The subscription id.')->required(),
        ];
    }
}
