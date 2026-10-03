<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Mcp\Requests;

use JayI\Impex\Domains\Run\Actions\ListRunOwnersAction;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;
use JayI\Impex\Domains\Run\Resources\RunOwnerResource;
use Laravel\Mcp\ResponseFactory;

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
