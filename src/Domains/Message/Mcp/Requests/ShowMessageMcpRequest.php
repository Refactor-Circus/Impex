<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Mcp\Requests;

use JayI\Impex\Domains\Message\Actions\ShowMessageAction;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Message\Resources\MessageResource;
use JayI\Impex\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

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
