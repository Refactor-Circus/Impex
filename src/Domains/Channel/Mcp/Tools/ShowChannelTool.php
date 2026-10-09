<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Requests\ShowChannelMcpRequest;

#[Description('Show one channel by name: its direction, transport, options and body policy. Secrets are never returned.')]
final class ShowChannelTool extends Tool
{
    public function handle(ShowChannelMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'channel' => $schema->string()->description('The channel name.')->required(),
        ];
    }
}
