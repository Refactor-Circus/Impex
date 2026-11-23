<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Http\Requests\ShowRunStatsRequest;

final class RunStatsController
{
    public function show(ShowRunStatsRequest $request): JsonResponse
    {
        return $request->persist();
    }
}
