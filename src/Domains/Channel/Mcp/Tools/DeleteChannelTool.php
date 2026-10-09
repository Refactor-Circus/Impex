<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Requests\DeleteChannelMcpRequest;

#[Description('Delete a stored channel. Its traffic stays in the ledger.')]
final class DeleteChannelTool extends Tool
{
    public function handle(DeleteChannelMcpRequest $request): Response|ResponseFactory
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
