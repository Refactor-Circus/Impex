<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Channel\Mcp\Requests\CreateChannelMcpRequest;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('Create a stored channel. Outbound http channels take options.url; mail channels options.to and options.mailer; file channels options.disk and options.path. Secrets go in credentials.signing_secret.')]
final class CreateChannelTool extends Tool
{
    public function handle(CreateChannelMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Lowercase name, used in URLs and on every ledger row. Never renamed.')->required(),
            'direction' => $schema->string()->enum(['inbound', 'outbound'])->required(),
            'transport' => $schema->string()->description('http, mail, file, or a registered custom transport.')->required(),
            'status' => $schema->string()->enum(['active', 'disabled']),
            'body_policy' => $schema->string()->enum(['all', 'failures', 'none'])->description('Which bodies the ledger keeps.'),
            'options' => $schema->object()->description('Transport settings.'),
            'credentials' => $schema->object()->description('Secrets, encrypted at rest.'),
        ];
    }
}
