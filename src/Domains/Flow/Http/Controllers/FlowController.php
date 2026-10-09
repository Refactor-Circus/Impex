<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Flow\Http\Requests\IndexFlowsRequest;
use RefactorCircus\Impex\Domains\Flow\Http\Requests\StoreFlowRunRequest;

final class FlowController
{
    public function index(IndexFlowsRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function run(StoreFlowRunRequest $request): JsonResponse
    {
        return $request->persist();
    }
}
