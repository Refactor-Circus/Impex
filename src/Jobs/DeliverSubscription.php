<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Services\Dispatcher;
use RefactorCircus\Impex\Support\Concerns\UsesConfiguredMiddleware;

/**
 * Delivers one subscription's pending events. Carries the subscription's id
 * and nothing else.
 */
final class DeliverSubscription implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesConfiguredMiddleware;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Seconds a waiting job holds its place, so one lost on the way to a
     * worker cannot stop deliveries for good: `impex:tick` queues another.
     */
    public int $uniqueFor = 300;

    public function __construct(public readonly string $subscriptionId) {}

    public function uniqueId(): string
    {
        return $this->subscriptionId;
    }

    public function uniqueFor(): int
    {
        return $this->uniqueFor;
    }

    public function handle(Dispatcher $dispatcher): void
    {
        $dispatcher->deliver($this->subscriptionId);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['impex', 'subscription:'.$this->subscriptionId];
    }
}
