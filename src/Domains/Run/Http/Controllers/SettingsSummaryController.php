<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Http\Requests\ShowSettingsSummaryRequest;

final class SettingsSummaryController
{
    public function show(ShowSettingsSummaryRequest $request): JsonResponse
    {
        return $request->persist();
    }
}
