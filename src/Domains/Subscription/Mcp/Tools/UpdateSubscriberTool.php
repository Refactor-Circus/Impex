<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\UpdateSubscriberMcpRequest;

#[Description('Change a subscriber\'s name, OAuth client, status or metadata.')]
final class UpdateSubscriberTool extends Tool
{
    public function handle(UpdateSubscriberMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscriber' => $schema->string()->description('The subscriber id.')->required(),
            'name' => $schema->string(),
            'client_id' => $schema->string(),
            'status' => $schema->string()->enum(['active', 'disabled']),
            'metadata' => $schema->object(),
        ];
    }
}
