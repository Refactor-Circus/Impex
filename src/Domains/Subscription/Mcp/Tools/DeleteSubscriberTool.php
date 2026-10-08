<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use JayI\Foundation\Mcp\Tool;
use JayI\Impex\Domains\Subscription\Mcp\Requests\DeleteSubscriberMcpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete a subscriber and every subscription it has.')]
final class DeleteSubscriberTool extends Tool
{
    public function handle(DeleteSubscriberMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscriber' => $schema->string()->description('The subscriber id.')->required(),
        ];
    }
}
