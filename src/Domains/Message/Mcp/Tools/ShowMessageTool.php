<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Message\Mcp\Requests\ShowMessageMcpRequest;

#[Description('Show one ledger message, including its headers and body preview.')]
final class ShowMessageTool extends Tool
{
    public function handle(ShowMessageMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()->description('The message id.')->required(),
        ];
    }
}
