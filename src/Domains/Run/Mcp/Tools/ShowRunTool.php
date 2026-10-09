<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Run\Mcp\Requests\ShowRunMcpRequest;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('Show one run with its owners and its recorded step history.')]
final class ShowRunTool extends Tool
{
    public function handle(ShowRunMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run' => $schema->string()->description('The run id.')->required(),
        ];
    }
}
