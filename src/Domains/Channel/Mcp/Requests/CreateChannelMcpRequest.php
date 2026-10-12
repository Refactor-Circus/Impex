<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Channel\Actions\CreateChannelAction;
use RefactorCircus\Impex\Domains\Channel\Data\CreateChannelData;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Resources\ChannelResource;
use RefactorCircus\Keystone\Mcp\Requests\Request;

final class CreateChannelMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', ChannelModel::class);
    }

    protected function rules(): array
    {
        return CreateChannelData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $channel = app(CreateChannelAction::class)->execute(CreateChannelData::fromValidated($validated), $this->actor());

        return (new ChannelResource($channel))->toStructuredResponse();
    }
}
