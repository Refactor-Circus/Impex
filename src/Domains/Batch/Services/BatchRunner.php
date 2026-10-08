<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Batch\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use JayI\Impex\Domains\Artifact\Enums\ArtifactKind;
use JayI\Impex\Domains\Artifact\Services\PayloadStore;
use JayI\Impex\Domains\Batch\Contracts\BatchSource;
use JayI\Impex\Domains\Batch\Data\BatchChunkItem;
use JayI\Impex\Domains\Batch\Exceptions\BatchFailedException;
use JayI\Impex\Domains\Batch\Exceptions\BatchItemAbandonedException;
use JayI\Impex\Domains\Batch\Models\BatchItemModel;
use JayI\Impex\Domains\Batch\Models\BatchModel;
use JayI\Impex\Domains\Run\Data\StepDeadline;
use JayI\Impex\Domains\Run\Enums\StepStatus;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Domains\Run\Services\Engine;
use JayI\Impex\Domains\Run\Services\EngineOptions;
use JayI\Impex\Domains\Run\Services\JobRouter;
use JayI\Impex\Support\Locks;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;

/**
 * Seeds, processes, and finalises batches.
 *
 * A batch is one step in the replay history however many items it holds. Per
 * item state lives in `impex_batch_items`, which the replay never reads, so the
 * cost of a drive is independent of the item count — the property that makes a
 * million-item sweep possible at all.
 */
final class BatchRunner
{
    /**
     * The item this process is running, and the lease it holds, so a timeout
     * can release it before the worker is killed.
     *
     * @var array{item: string, token: string, attempts: int}|null
     */
    private ?array $running = null;

    public function __construct(
        private readonly Container $container,
        private readonly PayloadStore $payloads,
        private readonly JobRouter $jobs,
        private readonly Locks $locks,
        private readonly EngineOptions $options,
    ) {}

    /**
     * Seed a page of work, resuming from the cursor and stopping before the
     * invocation's ceiling.
     */
    public function seed(string $batchId, ?string $cursor): void
    {
        $batch = BatchModel::query()->find($batchId);

        if (! $batch instanceof BatchModel || $batch->seeded) {
            return;
        }

        $source = $this->makeSource($batch);
        $deadline = StepDeadline::in($this->options->maxStepSeconds(), $this->options->resumeMargin());

        while (true) {
            $chunk = $source->chunk($cursor, $batch->chunk_size);

            $this->insert($batch, $chunk->items);

            if ($chunk->complete) {
                $batch->update(['seeded' => true]);

                $this->settle($batch->getKey());

                return;
            }

            if ($chunk->nextCursor === $cursor) {
                throw new RuntimeException(sprintf(
                    'The batch source [%s] returned the same cursor [%s] twice without completing, so it '.
                    'is not making progress.',
                    $batch->source,
                    $cursor ?? 'null',
                ));
            }

            $cursor = $chunk->nextCursor;

            // A Lambda timeout cannot be caught, so seeding stops short of the
            // ceiling and re-dispatches itself from the cursor it just stored.
            if ($deadline->reached()) {
                $this->jobs->seed($batch->run, $batchId, $cursor);

                return;
            }
        }
    }

    /**
     * Run one item's action, under the same lease discipline as a step.
     */
    public function processItem(string $itemId): void
    {
        $item = BatchItemModel::query()->find($itemId);

        if (! $item instanceof BatchItemModel || ! $item->status->isClaimable()) {
            return;
        }

        $batch = $item->batch;

        if (! $batch instanceof BatchModel) {
            return;
        }

        $token = (string) Str::ulid();

        $claimed = BatchItemModel::query()
            ->whereKey($item->getKey())
            ->whereIn('status', [
                StepStatus::Pending->value,
                StepStatus::Running->value,
                StepStatus::Failed->value,
            ])
            ->where(function (Builder $query): void {
                $query->whereNull('leased_until')->orWhere('leased_until', '<=', Carbon::now());
            })
            ->update([
                'status' => StepStatus::Running->value,
                'lease_token' => $token,
                'leased_until' => Carbon::now()->addSeconds($this->options->leaseSeconds()),
                'attempts' => $item->attempts + 1,
                'updated_at' => Carbon::now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $this->running = ['item' => (string) $item->getKey(), 'token' => $token, 'attempts' => $item->attempts + 1];

        try {
            $action = $this->container->make($batch->action);

            if (! is_object($action) || ! method_exists($action, 'execute')) {
                throw new RuntimeException(sprintf(
                    'The Impex action [%s] must be a class declaring a public execute() method.',
                    $batch->action,
                ));
            }

            $result = $action->execute($this->payloads->get($item->payload, $item->payload_artifact_id));
        } catch (Throwable $e) {
            $this->running = null;
            $this->failItem($batch, $item, $token, $item->attempts + 1, $e);

            return;
        }

        $this->running = null;

        $stored = $this->payloads->put($result, ArtifactKind::Result, ['run_id' => $batch->run_id]);

        $written = BatchItemModel::query()
            ->whereKey($item->getKey())
            ->where('lease_token', $token)
            ->update([
                'status' => StepStatus::Completed->value,
                'result' => json_encode($stored['inline']),
                'result_artifact_id' => $stored['artifact_id'],
                'lease_token' => null,
                'leased_until' => null,
                'updated_at' => Carbon::now(),
            ]);

        if ($written === 0) {
            return;
        }

        BatchModel::query()->whereKey($batch->getKey())->increment('succeeded');

        $this->settle((string) $batch->getKey());
    }

    /**
     * Finish the batch if every item has settled.
     *
     * Throttled by a short cache lock so a thousand items completing at once do
     * not each run the completion query; `impex:tick` sweeps any batch this
     * misses, so a skipped check only delays finalisation.
     */
    public function settle(string $batchId, bool $force = false): void
    {
        $lock = $this->locks->acquire('impex:batch:'.$batchId, 30);

        if (! $force && ! $lock->get()) {
            return;
        }

        try {
            $this->finalize($batchId);
        } finally {
            if (! $force) {
                $lock->release();
            }
        }
    }

    /**
     * Sweep batches whose completion check was throttled away.
     *
     * @return int the number finalised
     */
    /**
     * Give up the item this process is running, because the worker is about
     * to be killed for running past its timeout. The attempt counts, and the
     * item is retried or failed as for any other error — now, rather than
     * once its lease lapses.
     */
    public function abandonRunning(): void
    {
        $running = $this->running;
        $this->running = null;

        if ($running === null) {
            return;
        }

        $item = BatchItemModel::query()->find($running['item']);
        $batch = $item?->batch;

        if ($item instanceof BatchItemModel && $batch instanceof BatchModel) {
            $this->failItem($batch, $item, $running['token'], $running['attempts'], BatchItemAbandonedException::timedOut());
        }
    }

    /**
     * Take back items whose worker died mid-attempt — killed, out of memory,
     * a lost Lambda — once their lease has lapsed. Each such attempt counts,
     * so an item that keeps killing its worker fails rather than looping.
     */
    public function reclaimLeases(int $limit = 250): int
    {
        $items = BatchItemModel::query()
            ->where('status', StepStatus::Running)
            ->whereNotNull('leased_until')
            ->where('leased_until', '<=', Carbon::now())
            ->limit($limit)
            ->get();

        $reclaimed = 0;

        foreach ($items as $item) {
            $token = (string) Str::ulid();

            // Taken over with a fresh token only while still lapsed, so a
            // worker that finishes late, or another sweep, wins or loses
            // cleanly.
            $taken = BatchItemModel::query()
                ->whereKey($item->getKey())
                ->where('status', StepStatus::Running->value)
                ->where('leased_until', '<=', Carbon::now())
                ->update(['lease_token' => $token, 'updated_at' => Carbon::now()]);

            $batch = $item->batch;

            if ($taken === 0 || ! $batch instanceof BatchModel) {
                continue;
            }

            $this->failItem($batch, $item, $token, $item->attempts, BatchItemAbandonedException::leaseLapsed());
            $reclaimed++;
        }

        return $reclaimed;
    }

    public function sweep(int $limit = 100): int
    {
        $batches = BatchModel::query()
            ->where('seeded', true)
            ->whereNull('finalized_at')
            ->limit($limit)
            ->pluck('id');

        $finalized = 0;

        foreach ($batches as $batchId) {
            if ($this->finalize((string) $batchId)) {
                $finalized++;
            }
        }

        return $finalized;
    }

    private function finalize(string $batchId): bool
    {
        $batch = BatchModel::query()->find($batchId);

        if (! $batch instanceof BatchModel || ! $batch->seeded || $batch->finalized_at !== null) {
            return false;
        }

        $outstanding = BatchItemModel::query()
            ->where('batch_id', $batch->getKey())
            ->whereIn('status', [StepStatus::Pending, StepStatus::Running])
            ->exists();

        if ($outstanding) {
            return false;
        }

        $batch->update(['finalized_at' => Carbon::now()]);
        $batch->refresh();

        $step = RunStepModel::query()->find($batch->step_id);
        $run = RunModel::query()->find($batch->run_id);

        if (! $step instanceof RunStepModel || ! $run instanceof RunModel) {
            return false;
        }

        $summary = [
            'batch_id' => (string) $batch->getKey(),
            'total' => $batch->total,
            'succeeded' => $batch->succeeded,
            'failed' => $batch->failed,
        ];

        if ($batch->withinFailureThreshold()) {
            $stored = $this->payloads->put($summary, ArtifactKind::Result, ['run_id' => $batch->run_id]);

            $step->update([
                'status' => StepStatus::Completed,
                'result' => $stored['inline'],
                'result_artifact_id' => $stored['artifact_id'],
                'completed_at' => Carbon::now(),
            ]);
        } else {
            $error = BatchFailedException::threshold(
                $batch->action,
                $batch->failed,
                $batch->total,
                $batch->allow_failures,
            );

            $step->update([
                'status' => StepStatus::Failed,
                'error' => [
                    'class' => $error::class,
                    'message' => $error->getMessage(),
                    'summary' => $summary,
                ],
                'completed_at' => Carbon::now(),
            ]);
        }

        $this->container->make(Engine::class)->dispatchDrive($run);

        return true;
    }

    /**
     * @param  array<int, BatchChunkItem>  $items
     */
    private function insert(BatchModel $batch, array $items): void
    {
        if ($items === []) {
            return;
        }

        $inserted = 0;

        foreach ($items as $item) {
            $stored = $this->payloads->put($item->payload, ArtifactKind::Payload, [
                'run_id' => $batch->run_id,
            ]);

            // insertOrIgnore against unique(batch_id, item_key): a redelivered
            // seed writes nothing and dispatches nothing.
            $created = BatchItemModel::query()->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'batch_id' => $batch->getKey(),
                'item_key' => $item->key,
                'payload' => json_encode($stored['inline']),
                'payload_artifact_id' => $stored['artifact_id'],
                'status' => StepStatus::Pending->value,
                'attempts' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if ($created === 0) {
                continue;
            }

            $inserted++;

            $row = BatchItemModel::query()
                ->where('batch_id', $batch->getKey())
                ->where('item_key', $item->key)
                ->first();

            if ($row instanceof BatchItemModel) {
                $this->jobs->batchItem($batch->run, (string) $row->getKey());
            }
        }

        if ($inserted > 0) {
            BatchModel::query()->whereKey($batch->getKey())->increment('total', $inserted);
        }
    }

    /**
     * @param  int  $attempts  Attempts made, this one included.
     */
    private function failItem(BatchModel $batch, BatchItemModel $item, string $token, int $attempts, Throwable $error): void
    {
        $describe = [
            'class' => $error::class,
            'message' => $error->getMessage(),
        ];

        if ($attempts < $batch->max_attempts) {
            BatchItemModel::query()
                ->whereKey($item->getKey())
                ->where('lease_token', $token)
                ->update([
                    'status' => StepStatus::Pending->value,
                    'lease_token' => null,
                    'leased_until' => null,
                    'error' => json_encode($describe),
                    'updated_at' => Carbon::now(),
                ]);

            $this->jobs->batchItem($batch->run, (string) $item->getKey());

            return;
        }

        $written = BatchItemModel::query()
            ->whereKey($item->getKey())
            ->where('lease_token', $token)
            ->update([
                'status' => StepStatus::Failed->value,
                'lease_token' => null,
                'leased_until' => null,
                'error' => json_encode($describe),
                'updated_at' => Carbon::now(),
            ]);

        if ($written === 0) {
            return;
        }

        BatchModel::query()->whereKey($batch->getKey())->increment('failed');

        $this->settle((string) $batch->getKey());
    }

    private function makeSource(BatchModel $batch): BatchSource
    {
        /** @var array<int, mixed> $arguments */
        $arguments = (array) $this->payloads->get($batch->source_arguments, null);

        $source = $this->container->make(
            $batch->source,
            $this->nameArguments($batch->source, array_values($arguments)),
        );

        if (! $source instanceof BatchSource) {
            throw new RuntimeException(sprintf(
                'The batch source [%s] must implement %s.',
                $batch->source,
                BatchSource::class,
            ));
        }

        return $source;
    }

    /**
     * Key positional arguments by the constructor parameter they fill.
     *
     * The container matches extra make() arguments by parameter NAME, so a
     * positional list is silently ignored and the source is built entirely from
     * its defaults. Recovering the names keeps
     * `batch(Source::class, $a, $b)` working the way
     * `action(Action::class, $a, $b)` does, where the arguments are spread into
     * a method call and position is all that matters.
     *
     * @param  array<int, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function nameArguments(string $source, array $arguments): array
    {
        // The class name comes off a database column, so it is not known to
        // name anything real until it is checked. A miss falls through to
        // make(), which raises the binding error the caller needs to see.
        if ($arguments === [] || ! class_exists($source)) {
            return [];
        }

        $constructor = (new ReflectionClass($source))->getConstructor();

        if (! $constructor instanceof ReflectionMethod) {
            return [];
        }

        $named = [];

        foreach ($constructor->getParameters() as $position => $parameter) {
            if (! array_key_exists($position, $arguments)) {
                break;
            }

            // A variadic tail takes every remaining argument, and the container
            // cannot fill one by name — leave those to the source's defaults
            // rather than binding the list to the wrong parameter.
            if ($parameter->isVariadic()) {
                break;
            }

            $named[$parameter->getName()] = $arguments[$position];
        }

        return $named;
    }
}
