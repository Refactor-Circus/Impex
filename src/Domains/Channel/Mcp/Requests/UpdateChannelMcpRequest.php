<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Channel\Actions\UpdateChannelAction;
use RefactorCircus\Impex\Domains\Channel\Data\UpdateChannelData;
use RefactorCircus\Impex\Domains\Channel\Resources\ChannelResource;

final class UpdateChannelMcpRequest extends ChannelMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->channel());
    }

    protected function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'max:191'],
        ] + UpdateChannelData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $channel = app(UpdateChannelAction::class)->execute($this->channel(), UpdateChannelData::fromValidated($validated));

        return (new ChannelResource($channel))->toStructuredResponse();
    }
}
