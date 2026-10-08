<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Mcp\Requests;

use JayI\Impex\Domains\Channel\Actions\RotateChannelSecretAction;
use JayI\Impex\Domains\Channel\Resources\ChannelResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class RotateChannelSecretMcpRequest extends ChannelMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->channel());
    }

    protected function rules(): array
    {
        return RotateChannelSecretAction::rules() + [
            'channel' => ['required', 'string', 'max:191'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $rotated = app(RotateChannelSecretAction::class)->execute($this->channel());

        return Response::structured([
            ...(new ChannelResource($rotated['channel']))->resolve(),
            'secret' => $rotated['secret'],
        ]);
    }
}
