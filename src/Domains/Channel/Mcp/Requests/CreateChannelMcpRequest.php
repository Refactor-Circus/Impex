<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Mcp\Requests;

use JayI\Foundation\Mcp\Requests\Request;
use JayI\Impex\Domains\Channel\Actions\CreateChannelAction;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Channel\Resources\ChannelResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

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
