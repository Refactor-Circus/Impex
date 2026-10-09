<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Requests\ListChannelsMcpRequest;

#[Description('List the channels traffic enters and leaves through, configured and stored, with each one\'s transport and the flow an inbound one starts. Secrets are never returned.')]
final class ListChannelsTool extends Tool
{
    public function handle(ListChannelsMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'direction' => $schema->string()->enum(['inbound', 'outbound'])->description('Only channels in this direction.'),
        ];
    }
}
