<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Http\Requests\DestroySubscriberRequest;
use RefactorCircus\Impex\Domains\Subscription\Http\Requests\IndexSubscribersRequest;
use RefactorCircus\Impex\Domains\Subscription\Http\Requests\ShowSubscriberRequest;
use RefactorCircus\Impex\Domains\Subscription\Http\Requests\StoreSubscriberRequest;
use RefactorCircus\Impex\Domains\Subscription\Http\Requests\UpdateSubscriberRequest;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;

final class SubscriberController
{
    public function index(IndexSubscribersRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function store(StoreSubscriberRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function show(ShowSubscriberRequest $request, SubscriberModel $subscriber): JsonResponse
    {
        return $request->persist();
    }

    public function update(UpdateSubscriberRequest $request, SubscriberModel $subscriber): JsonResponse
    {
        return $request->persist();
    }

    public function destroy(DestroySubscriberRequest $request, SubscriberModel $subscriber): JsonResponse
    {
        return $request->persist();
    }
}
