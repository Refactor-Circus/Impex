<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\ListStreamsAction;
use Laravel\Mcp\ResponseFactory;

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
