<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Channel\Actions\DeleteChannelAction;

final class DeleteChannelMcpRequest extends ChannelMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('delete', $this->channel());
    }

    protected function rules(): array
    {
        return DeleteChannelAction::rules() + [
            'channel' => ['required', 'string', 'max:191'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        app(DeleteChannelAction::class)->execute($this->channel());

        return Response::structured(['deleted' => $validated['channel']]);
    }
}
