<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Foundation\Mcp\Requests\Request;
use RefactorCircus\Impex\Domains\Message\Actions\ShowMessageAction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Message\Resources\MessageResource;

final class ShowMessageMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return $this->allows('view', $this->message());
    }

    protected function rules(): array
    {
        return ShowMessageAction::rules() + [
            'message' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $message = app(ShowMessageAction::class)->execute($this->message());

        return Response::structured((new MessageResource($message))->resolve());
    }

    private function message(): MessageModel
    {
        /** @var string $id */
        $id = $this->get('message');

        return MessageModel::query()->findOrFail($id);
    }
}
