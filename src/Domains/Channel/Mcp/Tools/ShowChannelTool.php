<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use JayI\Foundation\Mcp\Tool;
use JayI\Impex\Domains\Channel\Mcp\Requests\ShowChannelMcpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

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
