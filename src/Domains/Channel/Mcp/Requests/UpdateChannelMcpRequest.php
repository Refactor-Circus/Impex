<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Mcp\Requests;

use JayI\Impex\Domains\Channel\Actions\UpdateChannelAction;
use JayI\Impex\Domains\Channel\Resources\ChannelResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

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
