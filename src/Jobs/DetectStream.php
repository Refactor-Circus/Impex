<?php

declare(strict_types=1);

namespace JayI\Impex\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JayI\Impex\Domains\Subscription\Services\Detector;
use JayI\Impex\Domains\Subscription\Services\SubscriptionJobs;
use JayI\Impex\Support\Concerns\UsesConfiguredMiddleware;

/**
 * Works through a stream's backlog of changes, chunk by chunk, until it is
 * empty or the time budget is spent — then queues itself again.
 *
 * At most one waits on the queue per stream and shard, however many touches
 * asked for it; several can run at once, sharing the work through leases.
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

    public function __construct(
        public readonly string $stream,
        public readonly int $shard = 0,
    ) {}

    public function uniqueId(): string
    {
        return $this->stream.':'.$this->shard;
    }

    public function handle(Detector $detector, SubscriptionJobs $jobs): void
    {
        $deadline = microtime(true) + $detector->timeBudget();

        while ($detector->run($this->stream) > 0) {
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
