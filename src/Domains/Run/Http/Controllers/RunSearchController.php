<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Http\Requests\IndexRunSearchRequest;

final class RunSearchController
{
    public function index(IndexRunSearchRequest $request): JsonResponse
    {
        return $request->persist();
    }
}
