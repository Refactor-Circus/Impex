<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RefactorCircus\Foundation\Models\Concerns\DispatchesModelEvents;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

/**
 * @property string $id
 * @property string $run_id
 * @property string $name
 * @property array<string, mixed>|null $payload
 * @property string|null $payload_artifact_id
 * @property string|null $idempotency_key
 * @property Carbon $delivered_at
 * @property Carbon|null $consumed_at
 * @property int|null $consumed_sequence
 */
final class SignalModel extends Model
{
    use DispatchesModelEvents;
    use HasUlids;

    protected $table = 'impex_signals';

    protected $fillable = [
        'run_id',
        'name',
        'payload',
        'payload_artifact_id',
        'idempotency_key',
        'delivered_at',
        'consumed_at',
        'consumed_sequence',
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
            'payload' => 'array',
            'delivered_at' => 'datetime',
            'consumed_at' => 'datetime',
            'consumed_sequence' => 'integer',
        ];
    }
}
