<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Foundation\Mcp\Requests\Request;
use RefactorCircus\Impex\Domains\Message\Actions\ListMessagesAction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Message\Resources\MessageResource;

final class ListMessagesMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', MessageModel::class);
    }

    protected function rules(): array
    {
        return ListMessagesAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $messages = app(ListMessagesAction::class)->execute($validated, $this->actor());

        return $this->structuredCollection(
            MessageResource::collection($messages)->resolve(),
            ['next_cursor' => $messages->nextCursor()?->encode()],
        );
    }
}
