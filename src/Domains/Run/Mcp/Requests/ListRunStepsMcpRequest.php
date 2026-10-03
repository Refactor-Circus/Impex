<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Mcp\Requests;

use JayI\Impex\Domains\Run\Actions\ListRunStepsAction;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Domains\Run\Resources\RunStepResource;
use Laravel\Mcp\ResponseFactory;

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
