<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Requests\UpdateChannelMcpRequest;

#[Description('Change a stored channel: its transport, status, body policy, options or credentials. The name and direction are fixed.')]
final class UpdateChannelTool extends Tool
{
    public function handle(UpdateChannelMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'channel' => $schema->string()->description('The channel name.')->required(),
            'transport' => $schema->string(),
            'status' => $schema->string()->enum(['active', 'disabled']),
            'body_policy' => $schema->string()->enum(['all', 'failures', 'none']),
            'options' => $schema->object(),
            'credentials' => $schema->object(),
        ];
    }
}
