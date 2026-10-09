<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RefactorCircus\Keystone\Models\Concerns\DispatchesModelEvents;

/**
 * @property string $id
 * @property string $run_id
 * @property string $owner_type
 * @property string $owner_id
 * @property string $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class RunOwnerModel extends Model
{
    use DispatchesModelEvents;
    use HasUlids;

    protected $table = 'impex_run_owners';

    protected $fillable = [
        'run_id',
        'owner_type',
        'owner_id',
        'role',
    ];

    /**
     * @return BelongsTo<RunModel, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(RunModel::class, 'run_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'owner_type', 'owner_id');
    }
}
