<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Mcp\Requests;

use JayI\Impex\Domains\Message\Actions\ListMessagesAction;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Message\Resources\MessageResource;
use JayI\Impex\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

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
