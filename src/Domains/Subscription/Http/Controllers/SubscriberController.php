<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Subscription\Http\Requests\DestroySubscriberRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\IndexSubscribersRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\ShowSubscriberRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\StoreSubscriberRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\UpdateSubscriberRequest;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

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
