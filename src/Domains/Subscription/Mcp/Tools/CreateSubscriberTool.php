<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\CreateSubscriberMcpRequest;

#[Description('Register a subscriber. client_id is the OAuth client its systems authenticate as on the subscriber API.')]
final class CreateSubscriberTool extends Tool
{
    public function handle(CreateSubscriberMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'client_id' => $schema->string(),
            'status' => $schema->string()->enum(['active', 'disabled']),
            'metadata' => $schema->object(),
        ];
    }
}
