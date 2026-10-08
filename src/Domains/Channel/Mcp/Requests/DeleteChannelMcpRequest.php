<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Mcp\Requests;

use JayI\Impex\Domains\Channel\Actions\DeleteChannelAction;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

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
