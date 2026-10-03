<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Run\Http\Requests\CancelRunRequest;
use JayI\Impex\Domains\Run\Http\Requests\IndexRunsRequest;
use JayI\Impex\Domains\Run\Http\Requests\RetryRunRequest;
use JayI\Impex\Domains\Run\Http\Requests\ShowRunRequest;
use JayI\Impex\Domains\Run\Models\RunModel;

final class RunController
{
    public function index(IndexRunsRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function show(ShowRunRequest $request, RunModel $run): JsonResponse
    {
        return $request->persist();
    }

    public function cancel(CancelRunRequest $request, RunModel $run): JsonResponse
    {
        return $request->persist();
    }

    public function retry(RetryRunRequest $request, RunModel $run): JsonResponse
    {
        return $request->persist();
    }
}
