<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

/**
 * A subscription failed too many times in a row and the circuit breaker
 * switched it off. Listen for this to tell its subscriber.
 */
final readonly class SubscriptionDisabled
{
    public function __construct(public string $subscriptionId) {}
}
