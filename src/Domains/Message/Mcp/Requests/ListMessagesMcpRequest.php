<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Message\Actions\ListMessagesAction;
use RefactorCircus\Impex\Domains\Message\Data\ListMessagesData;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Message\Resources\MessageResource;
use RefactorCircus\Keystone\Mcp\Requests\Request;

final class ListMessagesMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', MessageModel::class);
    }

    protected function rules(): array
    {
        return ListMessagesData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $messages = app(ListMessagesAction::class)->execute(ListMessagesData::fromValidated($validated), $this->actor());

        return MessageResource::collection($messages)->toStructuredResponse();
    }
}
