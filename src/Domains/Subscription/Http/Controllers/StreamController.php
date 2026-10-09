<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Http\Requests\IndexStreamsRequest;

final class StreamController
{
    public function __invoke(IndexStreamsRequest $request): JsonResponse
    {
        return $request->persist();
    }
}
