<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Contracts;

use Illuminate\Http\Request;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;

/**
 * Works out which subscriber an authenticated request comes from.
 *
 * Authentication itself is the application's: put its OAuth middleware in
 * `impex.routes.subscriber_middleware`. This only maps the result to a
 * subscriber. Bind your own to map users rather than clients.
 */
interface ResolvesSubscriber
{
    public function resolve(Request $request): ?SubscriberModel;
}
