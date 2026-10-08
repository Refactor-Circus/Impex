<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Subscription\Http\Requests\DestroySubscriptionRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\ExportSubscriptionRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\IndexDeliveriesRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\IndexSubscriptionEventsRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\IndexSubscriptionsRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\PingSubscriptionRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\RotateSubscriptionSecretRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\ShowSubscriptionRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\StoreSubscriptionRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\UpdateSubscriptionRequest;
use JayI\Impex\Domains\Subscription\Http\Requests\UpdateSubscriptionSubjectsRequest;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * Serves the operator API and the subscriber API alike; the requests tell
 * the two apart.
 */
final class SubscriptionController
{
    public function index(IndexSubscriptionsRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function show(ShowSubscriptionRequest $request, SubscriptionModel $subscription): JsonResponse
    {
        return $request->persist();
    }

    public function update(UpdateSubscriptionRequest $request, SubscriptionModel $subscription): JsonResponse
    {
        return $request->persist();
    }

    public function destroy(DestroySubscriptionRequest $request, SubscriptionModel $subscription): JsonResponse
    {
        return $request->persist();
    }

    public function ping(PingSubscriptionRequest $request, SubscriptionModel $subscription): JsonResponse
    {
        return $request->persist();
    }

    public function export(ExportSubscriptionRequest $request, SubscriptionModel $subscription): JsonResponse
    {
        return $request->persist();
    }

    public function rotateSecret(RotateSubscriptionSecretRequest $request, SubscriptionModel $subscription): JsonResponse
    {
        return $request->persist();
    }

    public function subjects(UpdateSubscriptionSubjectsRequest $request, SubscriptionModel $subscription): JsonResponse
    {
        return $request->persist();
    }

    public function events(IndexSubscriptionEventsRequest $request, SubscriptionModel $subscription): JsonResponse
    {
        return $request->persist();
    }

    public function deliveries(IndexDeliveriesRequest $request, SubscriptionModel $subscription): JsonResponse
    {
        return $request->persist();
    }
}
