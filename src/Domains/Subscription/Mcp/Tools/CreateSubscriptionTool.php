<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Requests\CreateSubscriptionMcpRequest;

#[Description('Subscribe a subscriber to a stream. Give endpoint.url to have events pushed, or leave endpoint out to have them pulled from the feed. Topics default to all. The signing secret is returned once.')]
final class CreateSubscriptionTool extends Tool
{
    public function handle(CreateSubscriptionMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscriber' => $schema->string()->description('The subscriber id.')->required(),
            'stream' => $schema->string()->required(),
            'topics' => $schema->array()->items($schema->string()),
            'format' => $schema->string()->description('thin, slice or full, or one the stream adds.'),
            'filter' => $schema->object()->description('Which subjects, in the stream\'s filter shape.'),
            'subjects' => $schema->array()->items($schema->string())->description('An explicit list of subject keys to follow instead.'),
            'endpoint' => $schema->object()->description('{url} for a webhook, or {transport: mail, to} for mail.'),
            'options' => $schema->object(),
        ];
    }
}
