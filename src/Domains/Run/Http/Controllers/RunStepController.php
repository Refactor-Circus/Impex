<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Run\Http\Requests\IndexRunStepsRequest;
use JayI\Impex\Domains\Run\Models\RunModel;

final class RunStepController
{
    public function index(IndexRunStepsRequest $request, RunModel $run): JsonResponse
    {
        return $request->persist();
    }
}
