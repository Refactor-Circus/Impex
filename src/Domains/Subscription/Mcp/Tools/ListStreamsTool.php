<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Subscription\Enums\StreamKind;
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

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->array()->items($schema->object([
                'key' => $schema->string()->required(),
                'kind' => $schema->string()->enum(array_column(StreamKind::cases(), 'value'))->required(),
                'topics' => $schema->array()->items($schema->string())->required(),
                'formats' => $schema->array()->items($schema->string())->required(),
                'filters' => $schema->array()->items($schema->string())->description('The fields a subscription filter can name.')->required(),
            ]))->required(),
        ];
    }
}
