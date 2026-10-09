<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RefactorCircus\Foundation\Models\Concerns\DispatchesModelEvents;
use RefactorCircus\Impex\Database\Factories\MessageFactory;
use RefactorCircus\Impex\Domains\Artifact\Models\ArtifactModel;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

/**
 * @property string $id
 * @property string|null $run_id
 * @property string|null $step_id
 * @property Direction $direction
 * @property string $channel
 * @property string $transport
 * @property string $endpoint
 * @property string|null $method
 * @property int|null $status_code
 * @property array<string, mixed>|null $headers
 * @property string|null $delivery_id
 * @property string|null $body
 * @property string|null $body_artifact_id
 * @property string|null $body_preview
 * @property string $body_encoding
 * @property string|null $body_sha256
 * @property int $bytes
 * @property bool|null $signature_valid
 * @property int|null $duration_ms
 * @property array<string, mixed>|null $error
 * @property string|null $idempotency_key
 * @property Carbon $occurred_at
 */
final class MessageModel extends Model
{
    /**
     * The full body is read through Impex::body(), which decodes it; listings
     * carry the preview.
     *
     * @var list<string>
     */
    protected $hidden = ['body'];

    use DispatchesModelEvents;

    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    use HasUlids;

    // Safe to mass prune: a message owns no external resource. Its body lives
    // on an Artifact, which prunes itself and deletes its own object.
    use MassPrunable;

    protected $table = 'impex_messages';

    protected $fillable = [
        'run_id',
        'step_id',
        'direction',
        'channel',
        'transport',
        'endpoint',
        'method',
        'status_code',
        'headers',
        'delivery_id',
        'body',
        'body_artifact_id',
        'body_preview',
        'body_encoding',
        'body_sha256',
        'bytes',
        'signature_valid',
        'duration_ms',
        'error',
        'idempotency_key',
        'occurred_at',
    ];

    /**
     * @return BelongsTo<RunModel, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(RunModel::class, 'run_id');
    }

    /**
     * @return BelongsTo<ArtifactModel, $this>
     */
    public function bodyArtifact(): BelongsTo
    {
        return $this->belongsTo(ArtifactModel::class, 'body_artifact_id');
    }

    /**
     * @return Builder<MessageModel>
     */
    public function prunable(): Builder
    {
        /** @var int $days */
        $days = config('impex.retention.messages_days', 90);

        return $this->newQuery()->where('occurred_at', '<=', Carbon::now()->subDays($days));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'headers' => 'array',
            'error' => 'array',
            'bytes' => 'integer',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'signature_valid' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }
}
