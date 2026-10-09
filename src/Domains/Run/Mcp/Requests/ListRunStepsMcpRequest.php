<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Actions\ListRunStepsAction;
use RefactorCircus\Impex\Domains\Run\Models\RunStepModel;
use RefactorCircus\Impex\Domains\Run\Resources\RunStepResource;

final class ListRunStepsMcpRequest extends RunRequest
{
    protected function authorize(): bool
    {
        return $this->allows('viewAny', RunStepModel::class, [$this->run()]);
    }

    protected function rules(): array
    {
        return ListRunStepsAction::rules() + [
            'run' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $steps = app(ListRunStepsAction::class)->execute($this->run(), $validated);

        return $this->structuredCollection(RunStepResource::collection($steps)->resolve());
    }
}
