<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Signal\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use JayI\Impex\Domains\Artifact\Enums\ArtifactKind;
use JayI\Impex\Domains\Artifact\Services\PayloadStore;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\StepPhase;
use JayI\Impex\Domains\Run\Enums\StepStatus;
use JayI\Impex\Domains\Run\Enums\StepType;
use JayI\Impex\Domains\Run\Exceptions\DeadlineExceededException;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Domains\Run\Services\EngineOptions;
use JayI\Impex\Domains\Run\Services\JobRouter;
use JayI\Impex\Domains\Run\Services\StepWriter;
use JayI\Impex\Domains\Run\Support\Failure;
use JayI\Impex\Domains\Signal\Enums\TimerKind;
use JayI\Impex\Domains\Signal\Exceptions\CannotSignalTerminalRunException;
use JayI\Impex\Domains\Signal\Models\SignalModel;
use JayI\Impex\Domains\Signal\Models\TimerModel;

/**
 * Everything a run waits on: signals, sleeps, and deadlines.
 *
 * A waiting run costs nothing — no worker, no connection, no queue message. It
 * is a row plus a timer telling the sweep when to look at it again, which is
 * the only way a multi-day wait is affordable on a platform that bills by the
 * invocation.
 */
final class Waits
{
    public function __construct(
        private readonly PayloadStore $payloads,
        private readonly StepWriter $steps,
        private readonly JobRouter $jobs,
        private readonly EngineOptions $options,
    ) {}

    /**
     * Deliver a signal to a run.
     *
     * Accepted by any unfinished run — pending, running, or waiting. One that
     * arrives before the flow reaches its wait is held, not lost.
     */
    public function deliver(RunModel $run, string $name, mixed $payload = null, ?string $idempotencyKey = null): SignalModel
    {
        if ($run->status->isFinished()) {
            throw CannotSignalTerminalRunException::status((string) $run->getKey(), $run->status);
        }

        $stored = $this->payloads->put($payload, ArtifactKind::Payload, ['run_id' => $run->getKey()]);

        $signal = SignalModel::query()->firstOrCreate(
            [
                'run_id' => $run->getKey(),
                'name' => $name,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'payload' => $stored['inline'],
                'payload_artifact_id' => $stored['artifact_id'],
                'delivered_at' => Carbon::now(),
            ],
        );

        $this->jobs->drive($run);

        return $signal;
    }

    /**
     * Consume a held signal, if one is waiting.
     *
     * @return array{payload: mixed}|null
     */
    public function consume(RunModel $run, string $name, int $sequence, ?RunStepModel $step): ?array
    {
        $signal = SignalModel::query()
            ->where('run_id', $run->getKey())
            ->where('name', $name)
            ->whereNull('consumed_at')
            ->orderBy('delivered_at')
            ->first();

        if (! $signal instanceof SignalModel) {
            return null;
        }

        $payload = $this->payloads->get($signal->payload, $signal->payload_artifact_id);

        if ($step instanceof RunStepModel) {
            $this->steps->resolve($step, $payload);
        } else {
            $this->steps->complete($run, $sequence, StepType::Signal, $name, $payload);
        }

        $signal->update(['consumed_at' => Carbon::now(), 'consumed_sequence' => $sequence]);

        return ['payload' => $payload];
    }

    /**
     * Record that the run is parked on a signal, with an optional deadline.
     */
    public function awaitSignal(RunModel $run, int $sequence, string $name, ?DateTimeInterface $timeout): void
    {
        $this->steps->pending($run, $sequence, StepType::Signal, $name);

        if ($timeout !== null) {
            $this->steps->timer($run, $sequence, TimerKind::SignalTimeout, $timeout);
        }
    }

    /**
     * Record that the run is sleeping until an instant.
     */
    public function sleep(RunModel $run, int $sequence, DateTimeInterface $until): void
    {
        $this->steps->pending($run, $sequence, StepType::Timer, 'sleep');

        $this->steps->timer($run, $sequence, TimerKind::Sleep, $until);
    }

    /**
     * Claim and fire timers that are due.
     *
     * The claim uses a lease predicate rather than a bare `claimed_at IS NULL`,
     * because a sweep that dies between claiming and dispatching would
     * otherwise strand the timer forever.
     *
     * @return int the number fired
     */
    public function sweep(?int $limit = null): int
    {
        $token = (string) Str::ulid();
        $horizon = Carbon::now()->subSeconds($this->options->timerClaimSeconds());

        // Select then update, rather than UPDATE ... LIMIT, which not every
        // driver supports.
        $ids = TimerModel::query()
            ->whereNull('fired_at')
            ->where('wake_at', '<=', Carbon::now())
            ->where(function (Builder $query) use ($horizon): void {
                $query->whereNull('claimed_at')->orWhere('claimed_at', '<=', $horizon);
            })
            ->orderBy('wake_at')
            ->limit($limit ?? $this->options->timerBatch())
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        TimerModel::query()->whereIn('id', $ids)->update([
            'claimed_at' => Carbon::now(),
            'claim_token' => $token,
        ]);

        $timers = TimerModel::query()->where('claim_token', $token)->whereNull('fired_at')->get();

        foreach ($timers as $timer) {
            $this->fire($timer);
        }

        return $timers->count();
    }

    /**
     * Fire one timer, resolving whatever it was holding open.
     */
    public function fire(TimerModel $timer): void
    {
        $step = RunStepModel::query()
            ->where('run_id', $timer->run_id)
            ->where('phase', StepPhase::Forward)
            ->where('sequence', $timer->sequence)
            ->first();

        if ($step instanceof RunStepModel && $step->status === StepStatus::Pending) {
            // Skipped, not completed-with-null: the flow has to be able to tell
            // "the deadline passed" from "the signal arrived carrying null".
            $step->update([
                'status' => $step->type === StepType::Signal
                    ? StepStatus::Skipped
                    : StepStatus::Completed,
                'result' => ['value' => null],
                'completed_at' => Carbon::now(),
            ]);
        }

        $timer->update(['fired_at' => Carbon::now()]);

        $run = RunModel::query()->find($timer->run_id);

        if ($run instanceof RunModel) {
            $this->jobs->drive($run);
        }
    }

    /**
     * Release leases on steps whose invocation never came back.
     *
     * Released rather than failed: it may have been a killed invocation, and a
     * resumable step still holds its cursor, so re-dispatching costs one window
     * of work rather than the whole step.
     *
     * @return int the number reclaimed
     */
    public function reclaimLeases(int $limit = 250): int
    {
        $steps = RunStepModel::query()
            ->where('status', StepStatus::Running)
            ->whereNotNull('leased_until')
            ->where('leased_until', '<=', Carbon::now())
            ->limit($limit)
            ->get();

        foreach ($steps as $step) {
            RunStepModel::query()->whereKey($step->getKey())->update([
                'status' => StepStatus::Pending->value,
                'lease_token' => null,
                'leased_until' => null,
            ]);

            $run = $step->run;

            if ($run instanceof RunModel && $run->status->isActive()) {
                $this->jobs->drive($run);
            }
        }

        return $steps->count();
    }

    /**
     * Fail steps and runs that passed their deadline.
     *
     * Enforced in the sweep rather than in-process: a step that has handed
     * control to an upstream call cannot check a clock, and a killed invocation
     * never gets the chance.
     *
     * @return array{steps: int, runs: int}
     */
    public function enforceDeadlines(int $limit = 250): array
    {
        $steps = RunStepModel::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->whereIn('status', [StepStatus::Pending, StepStatus::Running])
            ->limit($limit)
            ->get();

        $failedSteps = 0;

        foreach ($steps as $step) {
            $written = RunStepModel::query()
                ->whereKey($step->getKey())
                ->whereIn('status', [StepStatus::Pending->value, StepStatus::Running->value])
                ->update([
                    'status' => StepStatus::Failed->value,
                    'error' => json_encode(Failure::describe(
                        DeadlineExceededException::step($step->name, $step->sequence),
                    )),
                    'lease_token' => null,
                    'leased_until' => null,
                    'completed_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

            if ($written === 0) {
                continue;
            }

            $failedSteps++;

            $run = $step->run;

            if ($run instanceof RunModel) {
                $this->jobs->drive($run);
            }
        }

        $runs = RunModel::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->active()
            ->limit($limit)
            ->get();

        foreach ($runs as $run) {
            $run->update([
                'status' => RunStatus::RollingBack,
                'error' => Failure::describe(DeadlineExceededException::run($run->flow)),
            ]);

            $this->jobs->drive($run);
        }

        return ['steps' => $failedSteps, 'runs' => $runs->count()];
    }
}
