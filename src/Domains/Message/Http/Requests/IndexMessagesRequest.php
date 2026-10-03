<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Message\Actions\ListMessagesAction;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Message\Resources\MessageResource;
use JayI\Impex\Http\Request;

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
