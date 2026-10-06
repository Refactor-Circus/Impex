<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Mcp\Requests;

use JayI\Foundation\Mcp\Requests\Request;
use JayI\Impex\Domains\Message\Actions\ListChannelsAction;
use Laravel\Mcp\ResponseFactory;

final class ListChannelsMcpRequest extends Request
{
    protected function rules(): array
    {
        return ListChannelsAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        return $this->structuredCollection(app(ListChannelsAction::class)->execute());
    }
}
