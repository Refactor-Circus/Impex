<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\ExportSubscriptionMcpRequest;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('Queue a full export of everything a subscription covers, for a subscriber starting from scratch.')]
final class ExportSubscriptionTool extends Tool
{
    public function handle(ExportSubscriptionMcpRequest $request): Response|ResponseFactory
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
