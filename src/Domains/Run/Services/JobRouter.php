<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Services;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Jobs\DriveRun;
use JayI\Impex\Jobs\ExecuteStep;
use JayI\Impex\Jobs\ProcessBatchItem;
use JayI\Impex\Jobs\SeedBatch;

/**
 * Puts the engine's jobs on the right queue.
 *
 * Every job carries identifiers only, never payloads, so a message can never
 * approach SQS's 256KB limit however large a run's data is. Routing prefers the
 * run's own connection and queue, falling back to the package defaults, which
 * is what lets one flow be moved to a slower lane without touching config.
 */
final class JobRouter
{
    public function __construct(
        private readonly Bus $bus,
        private readonly EngineOptions $options,
    ) {}

    /**
     * Queue a drive of the run.
     */
    public function drive(RunModel $run): void
    {
        $this->bus->dispatch($this->route(new DriveRun((string) $run->getKey()), $run));
    }

    /**
     * Queue one step, optionally after a delay.
     */
    public function step(RunModel $run, RunStepModel $step, int $delay = 0): void
    {
        $job = new ExecuteStep((string) $run->getKey(), $step->phase->value, $step->sequence);

        if ($delay > 0) {
            $job->delay($delay);
        }

        $this->bus->dispatch($this->route($job, $run));
    }

    /**
     * Queue a batch seeding pass, resuming from a cursor.
     */
    public function seed(RunModel $run, string $batchId, ?string $cursor = null): void
    {
        $this->bus->dispatch($this->route(new SeedBatch($batchId, $cursor), $run));
    }

    /**
     * Queue one batch item.
     */
    public function batchItem(?RunModel $run, string $itemId, int $delay = 0): void
    {
        $job = new ProcessBatchItem($itemId);

        if ($delay > 0) {
            $job->delay($delay);
        }

        $this->bus->dispatch($run instanceof RunModel ? $this->route($job, $run) : $job);
    }

    /**
     * @template TJob of DriveRun|ExecuteStep|SeedBatch|ProcessBatchItem
     *
     * @param  TJob  $job
     * @return TJob
     */
    private function route(
        DriveRun|ExecuteStep|SeedBatch|ProcessBatchItem $job,
        RunModel $run,
    ): DriveRun|ExecuteStep|SeedBatch|ProcessBatchItem {
        $connection = $run->queue_connection ?? $this->options->queueConnection();
        $queue = $run->queue ?? $this->options->queueName();

        if ($connection !== null) {
            $job->onConnection($connection);
        }

        if ($queue !== null) {
            $job->onQueue($queue);
        }

        return $job;
    }
}
