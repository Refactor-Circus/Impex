<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Services\Detector;
use RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionJobs;
use RefactorCircus\Impex\Support\Concerns\UsesConfiguredMiddleware;

/**
 * Works through a stream's backlog of changes, chunk by chunk, until it is
 * empty or the time budget is spent — then queues itself again.
 *
 * At most one waits on the queue per stream and shard, however many touches
 * asked for it; several can run at once, sharing a snapshot stream's work
 * through leases. An append stream is fanned out by shard 0 alone, in order.
 */
final class DetectStream implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesConfiguredMiddleware;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Seconds a waiting job holds its place; set from
     * `impex.subscriptions.detection.lease_seconds` when queued.
     */
    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $stream,
        public readonly int $shard = 0,
    ) {}

    public function uniqueId(): string
    {
        return $this->stream.':'.$this->shard;
    }

    /**
     * How long a waiting job holds its place. A job lost on the way to a
     * worker must not stop detection for good: `impex:tick` queues another
     * once this lapses.
     */
    public function uniqueFor(): int
    {
        return $this->uniqueFor;
    }

    public function handle(Detector $detector, SubscriptionJobs $jobs): void
    {
        $deadline = microtime(true) + $detector->timeBudget();

        while ($detector->run($this->stream, $this->shard) > 0) {
            if (microtime(true) >= $deadline) {
                $jobs->detect($this->stream);

                return;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['impex', 'stream:'.$this->stream];
    }
}
