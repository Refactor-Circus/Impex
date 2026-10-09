<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\ListStreamsMcpRequest;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('List the streams subscribers can follow, with each one\'s topics, formats and filters.')]
final class ListStreamsTool extends Tool
{
    public function handle(ListStreamsMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [

        ];
    }
}
