<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Services;

use DateTimeInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JayI\Impex\Domains\Artifact\Enums\ArtifactKind;
use JayI\Impex\Domains\Artifact\Services\PayloadStore;
use JayI\Impex\Domains\Batch\Models\BatchModel;
use JayI\Impex\Domains\Flow\Contracts\Resumable;
use JayI\Impex\Domains\Flow\Exceptions\FlowVersionMismatchException;
use JayI\Impex\Domains\Flow\Services\FlowRegistry;
use JayI\Impex\Domains\Run\Contracts\RollbackStrategy;
use JayI\Impex\Domains\Run\Data\Resume;
use JayI\Impex\Domains\Run\Data\StepDeadline;
use JayI\Impex\Domains\Run\Data\StepDescriptor;
use JayI\Impex\Domains\Run\Enums\ChildClosePolicy;
use JayI\Impex\Domains\Run\Enums\RollbackFailure;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\StepPhase;
use JayI\Impex\Domains\Run\Enums\StepStatus;
use JayI\Impex\Domains\Run\Enums\StepType;
use JayI\Impex\Domains\Run\Events\RunCompleted;
use JayI\Impex\Domains\Run\Events\RunFailed;
use JayI\Impex\Domains\Run\Events\RunStarted;
use JayI\Impex\Domains\Run\Events\StepCompleted;
use JayI\Impex\Domains\Run\Events\StepFailed;
use JayI\Impex\Domains\Run\Exceptions\HistoryMismatchException;
use JayI\Impex\Domains\Run\Exceptions\StalledStepException;
use JayI\Impex\Domains\Run\Exceptions\StepFailedException;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Domains\Run\Support\Context;
use JayI\Impex\Domains\Run\Support\Failure;
use JayI\Impex\Domains\Run\Support\Suspended;
use JayI\Impex\Domains\Signal\Models\SignalModel;
use JayI\Impex\Domains\Signal\Models\TimerModel;
use JayI\Impex\Domains\Signal\Services\Waits;
use JayI\Impex\Support\Locks;
use RuntimeException;
use Throwable;

/**
 * Drives runs forward.
 *
 * Two entry points do all the work. `drive()` replays the flow and schedules
 * whatever it reaches that has not been recorded; `executeStep()` claims one
 * step under lease, runs it, and records the outcome. Neither ever waits, so no
 * single invocation approaches the Lambda ceiling however long the run lives.
 */
final class Engine
{
    public function __construct(
        private readonly Container $container,
        private readonly FlowRegistry $flows,
        private readonly PayloadStore $payloads,
        private readonly StepWriter $steps,
        private readonly Children $children,
        private readonly Waits $waits,
        private readonly RollbackStrategy $rollbacks,
        private readonly JobRouter $jobs,
        private readonly Locks $locks,
        private readonly EngineOptions $options,
        private readonly Events $events,
    ) {}

    public function payloads(): PayloadStore
    {
        return $this->payloads;
    }

    /**
     * Queue the first drive of a run.
     */
    public function start(RunModel $run): void
    {
        $this->jobs->drive($run);
    }

    /**
     * Replay the run and schedule whatever comes next.
     */
    public function drive(string $runId): void
    {
        $lock = $this->locks->acquire($this->options->lockKey($runId), $this->options->lockSeconds());

        if (! $lock->get()) {
            // Another invocation holds the run. Leave a marker so the holder
            // re-reads the history before it lets go, rather than blocking a
            // Lambda invocation on a lock we may never win.
            $this->locks->store()->put($this->options->dirtyKey($runId), true, $this->options->lockSeconds());

            return;
        }

        try {
            do {
                $this->locks->store()->forget($this->options->dirtyKey($runId));

                $this->driveOnce($runId);
            } while ($this->locks->store()->pull($this->options->dirtyKey($runId)) === true);
        } finally {
            $lock->release();
        }
    }

    /**
     * Claim one step, run it, and record the outcome.
     */
    public function executeStep(string $runId, string $phase, int $sequence): void
    {
        $step = RunStepModel::query()
            ->where('run_id', $runId)
            ->where('phase', $phase)
            ->where('sequence', $sequence)
            ->first();

        if (! $step instanceof RunStepModel || ! $step->status->isClaimable()) {
            return;
        }

        $token = (string) Str::ulid();

        // Claim BEFORE executing. A unique (run_id, phase, sequence) alone would
        // not make redelivery safe, because a record-after-execute step calls
        // the upstream before it ever loses an insert race. Reclaiming a lapsed
        // lease is what stops a timed-out invocation wedging the run.
        $claimed = RunStepModel::query()
            ->whereKey($step->getKey())
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
                'started_at' => $step->started_at ?? Carbon::now(),
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => Carbon::now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $step->refresh();

        try {
            $result = $this->invoke($step);
        } catch (Throwable $e) {
            $this->recordFailure($step, $token, $e);

            return;
        }

        if ($result instanceof Resume) {
            $this->recordResume($step, $token, $result);

            return;
        }

        $this->recordSuccess($step, $token, $result);
    }

    /**
     * Record an unrecorded operation and queue it.
     */
    public function scheduleStep(RunModel $run, int $sequence, StepDescriptor $descriptor): void
    {
        $step = $this->steps->write($run, StepPhase::Forward, $sequence, $descriptor);

        $this->jobs->step($run, $step);
    }

    /**
     * Record a fan-out marker: the collection's fingerprint and key order.
     *
     * Recording this is what turns a divergent collection into an immediate,
     * named failure instead of a silent misalignment between items and results.
     *
     * @param  array<int, int|string>  $keys
     */
    public function recordFanOut(RunModel $run, int $sequence, string $fingerprint, array $keys): void
    {
        $stored = $this->payloads->put([
            'fingerprint' => $fingerprint,
            'keys' => $keys,
            'count' => count($keys),
        ], ArtifactKind::Result, ['run_id' => $run->getKey()]);

        RunStepModel::query()->create([
            'run_id' => $run->getKey(),
            'phase' => StepPhase::Forward,
            'sequence' => $sequence,
            'type' => StepType::FanOut,
            'name' => 'fan-out',
            'status' => StepStatus::Completed,
            'result' => $stored['inline'],
            'result_artifact_id' => $stored['artifact_id'],
            'attempts' => 1,
            'max_attempts' => 1,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Record a batch as a single step and start seeding it.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function startBatch(
        RunModel $run,
        int $sequence,
        string $source,
        array $arguments,
        string $action,
        int $chunkSize,
        float $allowFailures,
        int $maxAttempts,
    ): void {
        $stored = $this->payloads->put($arguments, ArtifactKind::Payload, ['run_id' => $run->getKey()]);

        $step = RunStepModel::query()->create([
            'run_id' => $run->getKey(),
            'phase' => StepPhase::Forward,
            'sequence' => $sequence,
            'type' => StepType::Batch,
            'name' => $source,
            'status' => StepStatus::Pending,
            'input' => $stored['inline'],
            'input_artifact_id' => $stored['artifact_id'],
            'max_attempts' => 1,
            'queued_at' => Carbon::now(),
        ]);

        $batch = BatchModel::query()->create([
            'run_id' => $run->getKey(),
            'step_id' => $step->getKey(),
            'source' => $source,
            'source_arguments' => $stored['inline'],
            'action' => $action,
            'chunk_size' => $chunkSize,
            'allow_failures' => $allowFailures,
            'max_attempts' => $maxAttempts,
        ]);

        $this->jobs->seed($run, (string) $batch->getKey());
    }

    /**
     * Record a value the replay cannot recompute.
     */
    public function recordSideEffect(RunModel $run, int $sequence, string $key, mixed $value): void
    {
        $stored = $this->payloads->put($value, ArtifactKind::Result, ['run_id' => $run->getKey()]);

        RunStepModel::query()->create([
            'run_id' => $run->getKey(),
            'phase' => StepPhase::Forward,
            'sequence' => $sequence,
            'type' => StepType::SideEffect,
            'name' => $key,
            'status' => StepStatus::Completed,
            'result' => $stored['inline'],
            'result_artifact_id' => $stored['artifact_id'],
            'attempts' => 1,
            'max_attempts' => 1,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Record that the run is parked on a signal.
     */
    public function recordSignalWait(RunModel $run, int $sequence, string $name, ?DateTimeInterface $timeout): void
    {
        $this->waits->awaitSignal($run, $sequence, $name, $timeout);
    }

    /**
     * Consume a signal delivered before the run reached its wait.
     *
     * @return array{payload: mixed}|null
     */
    public function consumeSignal(RunModel $run, string $name, int $sequence, ?RunStepModel $step): ?array
    {
        return $this->waits->consume($run, $name, $sequence, $step);
    }

    /**
     * Record that the run is sleeping until an instant.
     */
    public function recordSleep(RunModel $run, int $sequence, DateTimeInterface $until): void
    {
        $this->waits->sleep($run, $sequence, $until);
    }

    /**
     * Deliver a signal to a run.
     */
    public function deliverSignal(RunModel $run, string $name, mixed $payload = null, ?string $idempotencyKey = null): SignalModel
    {
        return $this->waits->deliver($run, $name, $payload, $idempotencyKey);
    }

    /**
     * Fire one due timer.
     */
    public function fireTimer(TimerModel $timer): void
    {
        $this->waits->fire($timer);
    }

    /**
     * Fail steps and runs that passed their deadline.
     *
     * @return int the number failed
     */
    public function enforceDeadlines(int $limit = 250): int
    {
        $result = $this->waits->enforceDeadlines($limit);

        return $result['steps'] + $result['runs'];
    }

    /**
     * Start another flow as a child of this run.
     *
     * @param  array<int, mixed>  $arguments
     * @param  array<string, string>  $tags
     */
    public function startChild(
        RunModel $run,
        int $sequence,
        string $flow,
        array $arguments,
        ChildClosePolicy $closePolicy,
        array $tags,
        bool $detached,
    ): void {
        $this->children->start($run, $sequence, $flow, $arguments, $closePolicy, $tags, $detached);
    }

    /**
     * Drive a run to a terminal state in this process, within a time budget.
     *
     * For tests and for short flows behind a request. Steps are executed inline
     * rather than queued, so nothing here depends on a worker running. A run
     * that parks on a signal or a timer will not finish and is returned as-is.
     */
    public function driveToCompletion(RunModel $run, int $seconds): RunModel
    {
        $deadline = Carbon::now()->addSeconds(max(1, $seconds));

        while (Carbon::now()->lessThan($deadline)) {
            $this->drive((string) $run->getKey());

            $run->refresh();

            if ($run->status->isFinished()) {
                return $run;
            }

            $pending = RunStepModel::query()
                ->where('run_id', $run->getKey())
                ->whereIn('status', [StepStatus::Pending, StepStatus::Running])
                ->orderBy('phase')
                ->orderBy('sequence')
                ->get();

            if ($pending->isEmpty()) {
                // Nothing left to push: the run is parked on a signal or timer.
                return $run->refresh();
            }

            foreach ($pending as $step) {
                $this->executeStep((string) $run->getKey(), $step->phase->value, $step->sequence);
            }
        }

        return $run->refresh();
    }

    public function dispatchDrive(RunModel $run): void
    {
        $this->jobs->drive($run);
    }

    private function driveOnce(string $runId): void
    {
        $run = RunModel::query()->find($runId);

        if (! $run instanceof RunModel || $run->status->isFinished()) {
            return;
        }

        if ($run->status === RunStatus::RollingBack) {
            if (! $this->rollbacks->next($run)) {
                $this->failRun($run, null);
            }

            return;
        }

        if ($run->status === RunStatus::Pending) {
            $run->update(['status' => RunStatus::Running, 'started_at' => Carbon::now()]);

            $this->events->dispatch(new RunStarted((string) $run->getKey()));
        }

        // The slug may have been repointed at a different class since the run
        // started. The recorded history describes the original, so replaying
        // against the new one would be guesswork.
        if ($this->flows->has($run->flow) && $this->flows->class($run->flow) !== $run->flow_class) {
            $this->failRun($run, FlowVersionMismatchException::class(
                $run->flow,
                $run->flow_class,
                $this->flows->class($run->flow),
            ));

            return;
        }

        $context = new Context($run, $this->history($run), $this);

        try {
            $flow = $this->flows->make($run->flow_class)->withContext($context);

            /** @var array<int|string, mixed> $arguments */
            $arguments = (array) $this->payloads->get($run->input, $run->input_artifact_id);

            // Spread, not array_values: string keys are applied as named
            // arguments, so a run started with only `initial` set leaves every
            // other parameter on its declared default instead of being padded
            // with nulls that mean something different.
            /** @phpstan-ignore-next-line handle() is declared by the concrete flow */
            $result = $flow->handle(...$arguments);
        } catch (Suspended) {
            $this->applyTags($run, $context);

            $run->update([
                'status' => $context->isWaiting() ? RunStatus::Waiting : RunStatus::Running,
            ]);

            return;
        } catch (StepFailedException $e) {
            $this->applyTags($run, $context);

            $this->beginRollback($run, $e);

            return;
        } catch (HistoryMismatchException $e) {
            // Never roll back a divergence: the recorded history no longer
            // describes what the code does, so a rollback would be guesswork.
            $this->failRun($run, $e);

            return;
        } catch (Throwable $e) {
            $this->applyTags($run, $context);

            $this->beginRollback($run, $e);

            return;
        }

        $this->applyTags($run, $context);

        $this->completeRun($run, $result);
    }

    /**
     * @return array<int, RunStepModel>
     */
    private function history(RunModel $run): array
    {
        $history = [];

        foreach ($run->forwardSteps()->get() as $step) {
            $history[$step->sequence] = $step;
        }

        return $history;
    }

    private function applyTags(RunModel $run, Context $context): void
    {
        $tags = $context->tags();

        if ($tags === []) {
            return;
        }

        $run->update(['tags' => array_merge($run->tags ?? [], $tags)]);
    }

    private function completeRun(RunModel $run, mixed $result): void
    {
        $stored = $this->payloads->put($result, ArtifactKind::Result, ['run_id' => $run->getKey()]);

        $run->update([
            'status' => RunStatus::Completed,
            'result' => $stored['inline'],
            'result_artifact_id' => $stored['artifact_id'],
            'finished_at' => Carbon::now(),
        ]);

        $this->events->dispatch(new RunCompleted((string) $run->getKey()));

        $this->children->close($run);
        $this->children->notifyParent($run, $stored, null);
    }

    private function beginRollback(RunModel $run, Throwable $error): void
    {
        $run->update([
            'status' => RunStatus::RollingBack,
            'error' => Failure::describe($error),
        ]);

        if (! $this->rollbacks->next($run)) {
            $this->failRun($run, null);
        }
    }

    /**
     * Roll back the most recent reversible step that has not been undone.
     */
    /**
     * Whether a failed rollback should halt the rollback.
     *
     * Stopping is the default because a half-completed rollback that keeps
     * going can compound the damage.
     */
    public function rollbackHalts(RunStepModel $rollbackStep): bool
    {
        $target = RunStepModel::query()
            ->where('run_id', $rollbackStep->run_id)
            ->where('phase', StepPhase::Forward)
            ->where('sequence', $rollbackStep->undoes_sequence)
            ->first();

        if (! $target instanceof RunStepModel || ! is_array($target->rollback)) {
            return true;
        }

        return ($target->rollback['on_failure'] ?? RollbackFailure::Halt->value)
            === RollbackFailure::Halt->value;
    }

    private function failRun(RunModel $run, ?Throwable $error): void
    {
        $run->update([
            'status' => RunStatus::Failed,
            'error' => $error instanceof Throwable ? Failure::describe($error) : $run->error,
            'finished_at' => Carbon::now(),
        ]);

        $this->events->dispatch(new RunFailed((string) $run->getKey()));

        $this->children->close($run);
        $this->children->notifyParent($run, null, $error instanceof Throwable ? $error : null);
    }

    private function invoke(RunStepModel $step): mixed
    {
        $action = $this->container->make($step->name);

        if (! is_object($action) || ! method_exists($action, 'execute')) {
            throw new RuntimeException(sprintf(
                'The Impex action [%s] must be a class declaring a public execute() method.',
                $step->name,
            ));
        }

        if ($action instanceof Resumable) {
            $cursor = $step->cursor;

            $action->withCheckpoint(
                is_array($cursor) && is_string($cursor['value'] ?? null) ? $cursor['value'] : null,
                StepDeadline::in($this->options->maxStepSeconds(), $this->options->resumeMargin()),
            );
        }

        /** @var array<int, mixed> $arguments */
        $arguments = (array) $this->payloads->get($step->input, $step->input_artifact_id);

        return $action->execute(...array_values($arguments));
    }

    /**
     * The action ran out of time before it ran out of work: checkpoint and
     * re-dispatch the same step rather than completing or failing it.
     */
    private function recordResume(RunStepModel $step, string $token, Resume $resume): void
    {
        $previous = is_array($step->cursor) && is_string($step->cursor['value'] ?? null)
            ? $step->cursor['value']
            : null;

        // A cursor that has not moved means the action cannot make progress.
        // Looping forever would burn the account's Lambda concurrency, so this
        // fails loudly instead.
        if ($step->resumptions > 0 && $resume->cursor === $previous) {
            $this->recordFailure($step, $token, StalledStepException::cursor($step->name, $resume->cursor));

            return;
        }

        if ($step->resumptions >= $this->options->maxResumptions()) {
            $this->recordFailure($step, $token, StalledStepException::resumptions($step->name, $step->resumptions));

            return;
        }

        $written = RunStepModel::query()
            ->whereKey($step->getKey())
            ->where('lease_token', $token)
            ->update([
                'status' => StepStatus::Pending->value,
                'cursor' => json_encode(['value' => $resume->cursor]),
                'resumptions' => DB::raw('resumptions + 1'),
                'lease_token' => null,
                'leased_until' => null,
                'updated_at' => Carbon::now(),
            ]);

        if ($written === 0) {
            return;
        }

        $run = RunModel::query()->find($step->run_id);

        if ($run instanceof RunModel) {
            $this->jobs->step($run, $step, $resume->delaySeconds ?? 0);
        }
    }

    private function recordSuccess(RunStepModel $step, string $token, mixed $result): void
    {
        $stored = $this->payloads->put($result, ArtifactKind::Result, [
            'run_id' => $step->run_id,
            'step_id' => (string) $step->getKey(),
        ]);

        // Scoped to the lease token so a zombie that wakes after its lease
        // lapsed cannot clobber the winner's result.
        $written = RunStepModel::query()
            ->whereKey($step->getKey())
            ->where('lease_token', $token)
            ->update([
                'status' => StepStatus::Completed->value,
                'result' => json_encode($stored['inline']),
                'result_artifact_id' => $stored['artifact_id'],
                'completed_at' => Carbon::now(),
                'leased_until' => null,
                'updated_at' => Carbon::now(),
            ]);

        if ($written === 0) {
            return;
        }

        if ($step->phase === StepPhase::Rollback && $step->undoes_sequence !== null) {
            RunStepModel::query()
                ->where('run_id', $step->run_id)
                ->where('phase', StepPhase::Forward)
                ->where('sequence', $step->undoes_sequence)
                ->update(['undone' => true]);
        }

        $this->events->dispatch(new StepCompleted((string) $step->run_id, (string) $step->getKey()));

        $run = RunModel::query()->find($step->run_id);

        if ($run instanceof RunModel) {
            $this->dispatchDrive($run);
        }
    }

    private function recordFailure(RunStepModel $step, string $token, Throwable $error): void
    {
        $run = RunModel::query()->find($step->run_id);

        if ($step->attempts < $step->max_attempts) {
            RunStepModel::query()
                ->whereKey($step->getKey())
                ->where('lease_token', $token)
                ->update([
                    'status' => StepStatus::Pending->value,
                    'lease_token' => null,
                    'leased_until' => null,
                    'error' => json_encode(Failure::describe($error)),
                    'updated_at' => Carbon::now(),
                ]);

            if ($run instanceof RunModel) {
                $this->jobs->step($run, $step, $this->options->backoffSeconds($step->attempts));
            }

            return;
        }

        RunStepModel::query()
            ->whereKey($step->getKey())
            ->where('lease_token', $token)
            ->update([
                'status' => StepStatus::Failed->value,
                'lease_token' => null,
                'leased_until' => null,
                'error' => json_encode(Failure::describe($error)),
                'completed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        $this->events->dispatch(new StepFailed((string) $step->run_id, (string) $step->getKey()));

        if ($run instanceof RunModel) {
            $this->dispatchDrive($run);
        }
    }
}
