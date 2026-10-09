<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Actions\AttachRunOwnerAction;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Impex\Domains\Run\Resources\RunOwnerResource;

final class AttachRunOwnerMcpRequest extends RunRequest
{
    protected function authorize(): bool
    {
        return $this->allows('create', RunOwnerModel::class, [$this->run()]);
    }

    protected function rules(): array
    {
        return AttachRunOwnerAction::rules() + [
            'run' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $owner = app(AttachRunOwnerAction::class)->execute($this->run(), $validated);

        return Response::structured((new RunOwnerResource($owner))->resolve());
    }
}
