<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Foundation\Mcp\Requests\Request;
use RefactorCircus\Impex\Domains\Channel\Actions\CreateChannelAction;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Resources\ChannelResource;

final class CreateChannelMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', ChannelModel::class);
    }

    protected function rules(): array
    {
        return CreateChannelAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $channel = app(CreateChannelAction::class)->execute($validated, $this->actor());

        return Response::structured((new ChannelResource($channel))->resolve());
    }
}
