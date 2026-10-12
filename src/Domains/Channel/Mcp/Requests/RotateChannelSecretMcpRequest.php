<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Channel\Actions\RotateChannelSecretAction;
use RefactorCircus\Impex\Domains\Channel\Resources\ChannelResource;

final class RotateChannelSecretMcpRequest extends ChannelMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->channel());
    }

    protected function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'max:191'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $rotated = app(RotateChannelSecretAction::class)->execute($this->channel());

        return (new ChannelResource($rotated['channel']))
            ->additional(['secret' => $rotated['secret']])
            ->toStructuredResponse();
    }
}
