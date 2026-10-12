<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Services;

use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\Factory as Queues;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunStepModel;
use RefactorCircus\Impex\Jobs\DriveRun;
use RefactorCircus\Impex\Jobs\ExecuteStep;
use RefactorCircus\Impex\Jobs\ProcessBatchItem;
use RefactorCircus\Impex\Jobs\SeedBatch;

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
        private readonly Queues $queues,
        private readonly Cache $cache,
    ) {}

    /**
     * Queue a drive of the run, unless one is already waiting: a drive
     * replays the whole history when it starts, so the waiting one covers
     * this request too. One asked for while a drive is running is queued,
     * and the running one's dirty marker makes it look again besides.
     */
    public function drive(RunModel $run): void
    {
        $job = $this->route(new DriveRun((string) $run->getKey()), $run);
        $job->uniqueFor = $this->options->lockSeconds();

        // The bus does not take a unique job's lock itself; only the
        // dispatch() helper does.
        if (! (new UniqueLock($this->cache))->acquire($job)) {
            return;
        }

        $this->bus->dispatch($job);
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
     * Queue many batch items in one push: a seeded chunk of a thousand items
     * is one round trip to a queue that takes them in bulk, not a thousand.
     *
     * @param  list<string>  $itemIds
     */
    public function batchItems(?RunModel $run, array $itemIds): void
    {
        if ($itemIds === []) {
            return;
        }

        if (! $run instanceof RunModel) {
            foreach ($itemIds as $itemId) {
                $this->batchItem(null, $itemId);
            }

            return;
        }

        $connection = $run->queue_connection ?? $this->options->queueConnection();
        $queue = $run->queue ?? $this->options->queueName();

        $this->queues->connection($connection)->bulk(
            array_map(fn (string $itemId): ProcessBatchItem => $this->route(new ProcessBatchItem($itemId), $run), $itemIds),
            '',
            $queue,
        );
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
