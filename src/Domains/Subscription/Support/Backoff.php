<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Support;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * How long a failing subscription waits before its next attempt: doubling
 * from `base` up to `max`, with jitter so a thousand subscriptions failing
 * against the same outage do not all retry in the same second.
 */
final class Backoff
{
    public function __construct(private readonly Config $config) {}

    public function seconds(int $failures): int
    {
        /** @var int $base */
        $base = $this->config->get('impex.subscriptions.delivery.backoff.base', 10);

        /** @var int $max */
        $max = $this->config->get('impex.subscriptions.delivery.backoff.max', 3600);

        $delay = min($max, $base * (2 ** max(0, min($failures - 1, 20))));

        if ($this->config->get('impex.subscriptions.delivery.backoff.jitter', true) === true) {
            $delay = (int) round($delay * random_int(80, 120) / 100);
        }

        return max(1, min($max, $delay));
    }
}
