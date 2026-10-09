<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Channel\Actions\UpdateChannelAction;
use RefactorCircus\Impex\Domains\Channel\Resources\ChannelResource;

final class UpdateChannelMcpRequest extends ChannelMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->channel());
    }

    protected function rules(): array
    {
        return UpdateChannelAction::rules() + [
            'channel' => ['required', 'string', 'max:191'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        unset($validated['channel']);

        $channel = app(UpdateChannelAction::class)->execute($this->channel(), $validated);

        return Response::structured((new ChannelResource($channel))->resolve());
    }
}
