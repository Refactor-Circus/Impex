<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Impex\Domains\Message\Actions\ShowMessageAction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Message\Resources\MessageResource;

final class ShowMessageRequest extends Request
{
    public function authorize(): bool
    {
        return $this->allows('view', $this->message());
    }

    public function rules(): array
    {
        return ShowMessageAction::rules();
    }

    public function persist(): JsonResponse
    {
        return (new MessageResource(app(ShowMessageAction::class)->execute($this->message())))->response();
    }

    private function message(): MessageModel
    {
        $message = $this->route('message');

        if (! $message instanceof MessageModel) {
            abort(404);
        }

        return $message;
    }
}
