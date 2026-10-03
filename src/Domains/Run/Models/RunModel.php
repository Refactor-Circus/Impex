<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use JayI\Impex\Database\Factories\RunFactory;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Run\Enums\ChildClosePolicy;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\RunTrigger;
use JayI\Impex\Domains\Run\Enums\StepPhase;
use JayI\Impex\Domains\Signal\Models\SignalModel;
use JayI\Impex\Domains\Signal\Models\TimerModel;
use JayI\Impex\Support\Models\Concerns\DispatchesModelEvents;

/**
 * @property string $id
 * @property string $flow
 * @property string $flow_class
 * @property string|null $flow_version
 * @property RunStatus $status
 * @property RunTrigger $trigger
 * @property string|null $idempotency_key
 * @property array<string, mixed>|null $input
 * @property string|null $input_artifact_id
 * @property array<string, mixed>|null $result
 * @property string|null $result_artifact_id
 * @property array<string, mixed>|null $error
 * @property array<string, string>|null $tags
 * @property string|null $parent_run_id
 * @property int|null $parent_sequence
 * @property ChildClosePolicy|null $close_policy
 * @property string|null $queue_connection
 * @property string|null $queue
 * @property Carbon|null $expires_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class RunModel extends Model
{
    use DispatchesModelEvents;

    /** @use HasFactory<RunFactory> */
    use HasFactory;

    use HasUlids;
    use Prunable;

    protected $table = 'impex_runs';

    protected $fillable = [
        'flow',
        'flow_class',
        'flow_version',
        'status',
        'trigger',
        'idempotency_key',
        'input',
        'input_artifact_id',
        'result',
        'result_artifact_id',
        'error',
        'tags',
        'parent_run_id',
        'parent_sequence',
        'close_policy',
        'queue_connection',
        'queue',
        'expires_at',
        'started_at',
        'finished_at',
    ];

    /**
     * @return HasMany<RunStepModel, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RunStepModel::class, 'run_id');
    }

    /**
     * The forward history, in replay order.
     *
     * @return HasMany<RunStepModel, $this>
     */
    public function forwardSteps(): HasMany
    {
        return $this->steps()
            ->where('phase', StepPhase::Forward)
            ->orderBy('sequence');
    }

    /**
     * @return HasMany<RunOwnerModel, $this>
     */
    public function owners(): HasMany
    {
        return $this->hasMany(RunOwnerModel::class, 'run_id');
    }

    /**
     * @return HasMany<SignalModel, $this>
     */
    public function signals(): HasMany
    {
        return $this->hasMany(SignalModel::class, 'run_id');
    }

    /**
     * @return HasMany<TimerModel, $this>
     */
    public function timers(): HasMany
    {
        return $this->hasMany(TimerModel::class, 'run_id');
    }

    /**
     * @return HasMany<MessageModel, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(MessageModel::class, 'run_id');
    }

    /**
     * @return HasMany<RunModel, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(RunModel::class, 'parent_run_id');
    }

    /**
     * @return BelongsTo<RunModel, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(RunModel::class, 'parent_run_id');
    }

    /**
     * Scope to runs owned by the given model, in any role.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeWhereOwnedBy(Builder $query, Model $owner, ?string $role = null): void
    {
        $query->whereHas('owners', function (Builder $owners) use ($owner, $role): void {
            $owners->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getKey());

            if ($role !== null) {
                $owners->where('role', $role);
            }
        });
    }

    /**
     * Scope to runs owned by any of the given models.
     *
     * @param  Builder<$this>  $query
     * @param  iterable<int, Model>  $owners
     */
    public function scopeWhereOwnedByAny(Builder $query, iterable $owners): void
    {
        $pairs = [];

        foreach ($owners as $owner) {
            $pairs[] = [$owner->getMorphClass(), (string) $owner->getKey()];
        }

        if ($pairs === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('owners', function (Builder $query) use ($pairs): void {
            $query->where(function (Builder $query) use ($pairs): void {
                foreach ($pairs as [$type, $id]) {
                    $query->orWhere(function (Builder $query) use ($type, $id): void {
                        $query->where('owner_type', $type)->where('owner_id', $id);
                    });
                }
            });
        });
    }

    /**
     * Runs that may still be signalled.
     *
     * An alias of `active()`, and the scope to reach for when looking for a run
     * to signal: filtering by `running()` would miss exactly the runs that are
     * parked waiting for one.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSignalable(Builder $query): void
    {
        $this->scopeActive($query);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [
            RunStatus::Pending,
            RunStatus::Running,
            RunStatus::Waiting,
            RunStatus::RollingBack,
        ]);
    }

    /**
     * @return Builder<RunModel>
     */
    public function prunable(): Builder
    {
        /** @var int $completed */
        $completed = config('impex.retention.completed_runs_days', 90);

        /** @var int $failed */
        $failed = config('impex.retention.failed_runs_days', 365);

        return $this->newQuery()
            ->where(function (Builder $query) use ($completed, $failed): void {
                $query->where(function (Builder $query) use ($completed): void {
                    $query->whereIn('status', [RunStatus::Completed, RunStatus::Cancelled])
                        ->where('finished_at', '<=', Carbon::now()->subDays($completed));
                })->orWhere(function (Builder $query) use ($failed): void {
                    $query->where('status', RunStatus::Failed)
                        ->where('finished_at', '<=', Carbon::now()->subDays($failed));
                });
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'trigger' => RunTrigger::class,
            'close_policy' => ChildClosePolicy::class,
            'input' => 'array',
            'result' => 'array',
            'error' => 'array',
            'tags' => 'array',
            'parent_sequence' => 'integer',
            'expires_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function newFactory(): RunFactory
    {
        return RunFactory::new();
    }
}
