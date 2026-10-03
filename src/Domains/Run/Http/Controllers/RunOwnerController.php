<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use JayI\Impex\Domains\Run\Http\Requests\DeleteRunOwnerRequest;
use JayI\Impex\Domains\Run\Http\Requests\IndexRunOwnersRequest;
use JayI\Impex\Domains\Run\Http\Requests\StoreRunOwnerRequest;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;

final class RunOwnerController
{
    public function index(IndexRunOwnersRequest $request, RunModel $run): JsonResponse
    {
        return $request->persist();
    }

    public function store(StoreRunOwnerRequest $request, RunModel $run): JsonResponse
    {
        return $request->persist();
    }

    public function destroy(DeleteRunOwnerRequest $request, RunModel $run, RunOwnerModel $owner): Response
    {
        return $request->persist();
    }
}
