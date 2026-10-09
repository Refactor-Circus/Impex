<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use RefactorCircus\Impex\Domains\Artifact\Enums\ArtifactKind;
use RefactorCircus\Impex\Domains\Artifact\Services\PayloadStore;
use RefactorCircus\Impex\Domains\Run\Data\StepDescriptor;
use RefactorCircus\Impex\Domains\Run\Enums\StepPhase;
use RefactorCircus\Impex\Domains\Run\Enums\StepStatus;
use RefactorCircus\Impex\Domains\Run\Enums\StepType;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunStepModel;
use RefactorCircus\Impex\Domains\Signal\Enums\TimerKind;
use RefactorCircus\Impex\Domains\Signal\Models\TimerModel;

/**
 * Writes the replay history.
 *
 * Every recorded step goes through here, so the rules that make replay safe —
 * payload offload above the inline threshold, the rollback captured at record
 * time, the default deadline — are applied in exactly one place rather than at
 * each call site.
 */
final class StepWriter
{
    public function __construct(
        private readonly PayloadStore $payloads,
        private readonly EngineOptions $options,
    ) {}

    /**
     * Record a step the engine is about to schedule.
     */
    public function write(
        RunModel $run,
        StepPhase $phase,
        int $sequence,
        StepDescriptor $descriptor,
        ?int $undoes = null,
    ): RunStepModel {
        $stored = $this->payloads->put($descriptor->arguments, ArtifactKind::Payload, [
            'run_id' => $run->getKey(),
        ]);

        return RunStepModel::query()->create([
            'run_id' => $run->getKey(),
            'phase' => $phase,
            'sequence' => $sequence,
            'type' => $descriptor->type,
            'name' => $descriptor->name,
            'status' => StepStatus::Pending,
            'input' => $stored['inline'],
            'input_artifact_id' => $stored['artifact_id'],
            // Captured now, so unwinding never has to replay the flow to learn
            // what to undo.
            'rollback' => $descriptor->rollback === null ? null : $descriptor->rollback + [
                'on_failure' => $descriptor->rollbackFailure->value,
                'together' => $descriptor->rollbackTogether,
            ],
            'max_attempts' => $descriptor->maxAttempts,
            'undoes_sequence' => $undoes,
            'unit_id' => $descriptor->unitId,
            'expires_at' => $descriptor->expiresAt ?? $this->options->defaultStepDeadline(),
            'queued_at' => Carbon::now(),
        ]);
    }

    /**
     * Record a step that is already finished, with its result.
     */
    public function complete(
        RunModel $run,
        int $sequence,
        StepType $type,
        string $name,
        mixed $result,
    ): RunStepModel {
        $stored = $this->payloads->put($result, ArtifactKind::Result, ['run_id' => $run->getKey()]);

        return RunStepModel::query()->create([
            'run_id' => $run->getKey(),
            'phase' => StepPhase::Forward,
            'sequence' => $sequence,
            'type' => $type,
            'name' => $name,
            'status' => StepStatus::Completed,
            'result' => $stored['inline'],
            'result_artifact_id' => $stored['artifact_id'],
            'attempts' => 1,
            'max_attempts' => 1,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Resolve a step that was already recorded, with its result.
     */
    public function resolve(RunStepModel $step, mixed $result): void
    {
        $stored = $this->payloads->put($result, ArtifactKind::Result, ['run_id' => $step->run_id]);

        $step->update([
            'status' => StepStatus::Completed,
            'result' => $stored['inline'],
            'result_artifact_id' => $stored['artifact_id'],
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Record a step the run is now parked on.
     */
    public function pending(RunModel $run, int $sequence, StepType $type, string $name): RunStepModel
    {
        return RunStepModel::query()->create([
            'run_id' => $run->getKey(),
            'phase' => StepPhase::Forward,
            'sequence' => $sequence,
            'type' => $type,
            'name' => $name,
            'status' => StepStatus::Pending,
            'max_attempts' => 1,
            'queued_at' => Carbon::now(),
        ]);
    }

    /**
     * Record a future wake-up.
     *
     * A timer row rather than a delayed job, because the queue's delay ceiling
     * is far shorter than the waits a flow can express.
     */
    public function timer(RunModel $run, int $sequence, TimerKind $kind, DateTimeInterface $wakeAt): void
    {
        TimerModel::query()->create([
            'run_id' => $run->getKey(),
            'phase' => StepPhase::Forward,
            'sequence' => $sequence,
            'kind' => $kind,
            'wake_at' => $wakeAt,
        ]);
    }
}
