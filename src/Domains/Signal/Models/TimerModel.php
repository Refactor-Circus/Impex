<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RefactorCircus\Foundation\Models\Concerns\DispatchesModelEvents;
use RefactorCircus\Impex\Domains\Run\Enums\StepPhase;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Signal\Enums\TimerKind;

/**
 * @property string $id
 * @property string $run_id
 * @property StepPhase|null $phase
 * @property int|null $sequence
 * @property TimerKind $kind
 * @property Carbon $wake_at
 * @property Carbon|null $claimed_at
 * @property string|null $claim_token
 * @property Carbon|null $fired_at
 */
final class TimerModel extends Model
{
    use DispatchesModelEvents;
    use HasUlids;

    protected $table = 'impex_timers';

    protected $fillable = [
        'run_id',
        'phase',
        'sequence',
        'kind',
        'wake_at',
        'claimed_at',
        'claim_token',
        'fired_at',
    ];

    /**
     * @return BelongsTo<RunModel, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(RunModel::class, 'run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase' => StepPhase::class,
            'kind' => TimerKind::class,
            'sequence' => 'integer',
            'wake_at' => 'datetime',
            'claimed_at' => 'datetime',
            'fired_at' => 'datetime',
        ];
    }
}
