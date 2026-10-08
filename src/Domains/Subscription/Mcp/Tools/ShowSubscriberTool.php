<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use JayI\Foundation\Mcp\Tool;
use JayI\Impex\Domains\Subscription\Mcp\Requests\ShowSubscriberMcpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show one subscriber and how many subscriptions it has.')]
final class ShowSubscriberTool extends Tool
{
    public function handle(ShowSubscriberMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscriber' => $schema->string()->description('The subscriber id.')->required(),
        ];
    }
}
