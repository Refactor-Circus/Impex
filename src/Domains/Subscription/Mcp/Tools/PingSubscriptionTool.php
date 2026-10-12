<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\PingSubscriptionMcpRequest;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('Send a signed, empty ping to a subscription\'s endpoint and report how it answered.')]
final class PingSubscriptionTool extends Tool
{
    public function handle(PingSubscriptionMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscription' => $schema->string()->description('The subscription id.')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'successful' => $schema->boolean()->required(),
            'status_code' => $schema->integer()->nullable()->required(),
            'duration_ms' => $schema->integer()->nullable()->required(),
            'error' => $schema->object()->nullable()->required(),
            'message_id' => $schema->string()->description('The ledger message the ping was recorded as.')->required(),
        ];
    }
}
