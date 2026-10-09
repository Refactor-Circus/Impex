<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Signal\Http\Requests\StoreRunSignalRequest;

final class RunSignalController
{
    public function store(StoreRunSignalRequest $request, RunModel $run): JsonResponse
    {
        return $request->persist();
    }
}
