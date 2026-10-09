<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Contracts;

use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * Decides which filtered subscriptions a set of changed subjects falls into.
 *
 * Called once per chunk of changes with every active filtered subscription to
 * the stream, so build any index over the subscriptions' filters once, then
 * test each subject against it. Never load a subscription's whole scope: at
 * ten million subjects, a category subscription is millions of rows.
 */
interface SubscriptionMatcher
{
    /**
     * @param  array<string, array<string, mixed>>  $snapshots  Keyed by subject key.
     * @param  list<SubscriptionModel>  $subscriptions
     * @return array<string, list<string>> Subject keys, by subscription id.
     */
    public function match(array $snapshots, array $subscriptions): array;
}
