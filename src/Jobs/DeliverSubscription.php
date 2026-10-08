<?php

declare(strict_types=1);

namespace JayI\Impex\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JayI\Impex\Domains\Subscription\Services\Dispatcher;
use JayI\Impex\Support\Concerns\UsesConfiguredMiddleware;

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

    public function __construct(public readonly string $subscriptionId) {}

    public function uniqueId(): string
    {
        return $this->subscriptionId;
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
