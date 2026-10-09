<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Run\Mcp\Requests\RetryRunMcpRequest;

#[Description('Re-queue a drive for a failed or stalled run. Completed steps are not re-executed.')]
final class RetryRunTool extends Tool
{
    public function handle(RetryRunMcpRequest $request): Response|ResponseFactory
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
