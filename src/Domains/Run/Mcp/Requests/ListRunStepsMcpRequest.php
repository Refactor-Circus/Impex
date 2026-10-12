<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Actions\ListRunStepsAction;
use RefactorCircus\Impex\Domains\Run\Data\ListRunStepsData;
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
        return [
            'run' => ['required', 'string', 'max:26'],
        ] + ListRunStepsData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $steps = app(ListRunStepsAction::class)->execute($this->run(), ListRunStepsData::fromValidated($validated));

        return RunStepResource::collection($steps)->toStructuredResponse();
    }
}
