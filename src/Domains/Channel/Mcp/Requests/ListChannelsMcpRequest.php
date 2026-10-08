<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Mcp\Requests;

use JayI\Foundation\Mcp\Requests\Request;
use JayI\Impex\Domains\Channel\Actions\ListChannelsAction;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use Laravel\Mcp\ResponseFactory;

final class ListChannelsMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', ChannelModel::class);
    }

    protected function rules(): array
    {
        return ListChannelsAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        return $this->structuredCollection(app(ListChannelsAction::class)->execute($validated, $this->actor()));
    }
}
