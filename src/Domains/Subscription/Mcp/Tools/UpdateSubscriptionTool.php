<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\UpdateSubscriptionMcpRequest;

#[Description('Change a subscription. status active resumes a paused or disabled one; replay_from redelivers from that event on.')]
final class UpdateSubscriptionTool extends Tool
{
    public function handle(UpdateSubscriptionMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscription' => $schema->string()->description('The subscription id.')->required(),
            'topics' => $schema->array()->items($schema->string()),
            'format' => $schema->string(),
            'filter' => $schema->object(),
            'options' => $schema->object(),
            'status' => $schema->string()->enum(['active', 'paused']),
            'replay_from' => $schema->integer()->min(1),
            'endpoint' => $schema->object(),
        ];
    }
}
