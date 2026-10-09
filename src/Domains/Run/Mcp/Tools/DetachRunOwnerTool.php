<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Run\Mcp\Requests\DetachRunOwnerMcpRequest;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('Remove a model\'s stake in a run.')]
final class DetachRunOwnerTool extends Tool
{
    public function handle(DetachRunOwnerMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run' => $schema->string()->description('The run id.')->required(),
            'owner' => $schema->string()->description('The owner record id, from list-run-owners.')->required(),
        ];
    }
}
