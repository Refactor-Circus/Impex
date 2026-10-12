<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Message\Data\ListMessagesData;
use RefactorCircus\Impex\Domains\Message\Mcp\Requests\ListMessagesMcpRequest;
use RefactorCircus\Impex\Domains\Message\Resources\MessageResource;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('List the data-flow ledger: every payload that has crossed the application boundary, inbound or outbound. Cursor paginated.')]
final class ListMessagesTool extends Tool
{
    public function handle(ListMessagesMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'direction' => $schema->string()->enum(['inbound', 'outbound']),
            'channel' => $schema->string()->description('Only messages on this channel.'),
            'run' => $schema->string()->description('Only messages linked to this run id.'),
            'since' => $schema->string()->description('Only messages at or after this date.'),
            'until' => $schema->string()->description('Only messages at or before this date.'),
        ] + ListMessagesData::pagination()->schema($schema);
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return MessageResource::collectionSchema($schema, ListMessagesData::pagination());
    }
}
