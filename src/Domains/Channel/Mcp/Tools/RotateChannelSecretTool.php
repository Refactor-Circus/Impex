<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Requests\RotateChannelSecretMcpRequest;

#[Description('Give a stored channel a new signing secret, keeping the previous one valid until the next rotation. The new secret is returned once.')]
final class RotateChannelSecretTool extends Tool
{
    public function handle(RotateChannelSecretMcpRequest $request): Response|ResponseFactory
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
