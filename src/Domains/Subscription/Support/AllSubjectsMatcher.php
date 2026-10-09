<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Support;

use RefactorCircus\Impex\Domains\Subscription\Contracts\SubscriptionMatcher;

/**
 * For a stream with no filters: a filtered subscription matches everything.
 */
final class AllSubjectsMatcher implements SubscriptionMatcher
{
    public function match(array $snapshots, array $subscriptions): array
    {
        $keys = array_map('strval', array_keys($snapshots));
        $matches = [];

        foreach ($subscriptions as $subscription) {
            $matches[$subscription->id] = $keys;
        }

        return $matches;
    }
}
