<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use JayI\Foundation\Mcp\Tool;
use JayI\Impex\Domains\Subscription\Mcp\Requests\UpdateSubscriptionSubjectsMcpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add keys to, or remove them from, a subscription\'s explicit subject list.')]
final class UpdateSubscriptionSubjectsTool extends Tool
{
    public function handle(UpdateSubscriptionSubjectsMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscription' => $schema->string()->description('The subscription id.')->required(),
            'add' => $schema->array()->items($schema->string()),
            'remove' => $schema->array()->items($schema->string()),
        ];
    }
}
