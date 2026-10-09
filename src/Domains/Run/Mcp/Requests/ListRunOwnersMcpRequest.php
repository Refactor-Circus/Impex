<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Actions\ListRunOwnersAction;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Impex\Domains\Run\Resources\RunOwnerResource;

final class ListRunOwnersMcpRequest extends RunRequest
{
    protected function authorize(): bool
    {
        return $this->allows('viewAny', RunOwnerModel::class, [$this->run()]);
    }

    protected function rules(): array
    {
        return ListRunOwnersAction::rules() + [
            'run' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        return $this->structuredCollection(
            RunOwnerResource::collection(app(ListRunOwnersAction::class)->execute($this->run()))->resolve(),
        );
    }
}
