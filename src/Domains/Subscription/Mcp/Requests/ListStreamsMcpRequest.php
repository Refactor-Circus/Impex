<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListStreamsAction;

final class ListStreamsMcpRequest extends SubscriptionMcpRequest
{
    protected function rules(): array
    {
        return ListStreamsAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        return $this->structuredCollection(app(ListStreamsAction::class)->execute());
    }
}
