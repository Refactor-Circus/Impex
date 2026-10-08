<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Enums;

/**
 * Active subscriptions are delivered to. Paused ones collect events and
 * deliver nothing until resumed. Disabled ones were switched off by the
 * circuit breaker after failing too often; resuming one clears that.
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Disabled = 'disabled';
}
