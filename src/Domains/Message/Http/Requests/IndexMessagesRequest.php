<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Impex\Domains\Message\Actions\ListMessagesAction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Message\Resources\MessageResource;

final class IndexMessagesRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', MessageModel::class);
    }

    public function rules(): array
    {
        return ListMessagesAction::rules();
    }

    public function persist(): JsonResponse
    {
        $messages = app(ListMessagesAction::class)->execute($this->validated(), $this->actor());

        return MessageResource::collection($messages)->response();
    }
}
