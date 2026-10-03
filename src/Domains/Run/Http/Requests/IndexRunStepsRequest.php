<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Run\Actions\ListRunStepsAction;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Domains\Run\Resources\RunStepResource;

final class IndexRunStepsRequest extends RunRequest
{
    public function authorize(): bool
    {
        return $this->allows('viewAny', RunStepModel::class, [$this->run()]);
    }

    public function rules(): array
    {
        return ListRunStepsAction::rules();
    }

    public function persist(): JsonResponse
    {
        $steps = app(ListRunStepsAction::class)->execute($this->run(), $this->validated());

        return RunStepResource::collection($steps)->response();
    }
}
