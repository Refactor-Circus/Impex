<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Foundation\Mcp\Requests\Request;
use RefactorCircus\Impex\Domains\Channel\Actions\ListChannelsAction;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;

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
