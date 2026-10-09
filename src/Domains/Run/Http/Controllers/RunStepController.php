<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Http\Requests\IndexRunStepsRequest;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

final class RunStepController
{
    public function index(IndexRunStepsRequest $request, RunModel $run): JsonResponse
    {
        return $request->persist();
    }
}
