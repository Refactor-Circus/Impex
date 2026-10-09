<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Artifact\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RefactorCircus\Foundation\Models\Concerns\DispatchesModelEvents;
use RefactorCircus\Impex\Database\Factories\ArtifactFactory;
use RefactorCircus\Impex\Domains\Artifact\Enums\ArtifactKind;

/**
 * @property string $id
 * @property string $disk
 * @property string $path
 * @property ArtifactKind $kind
 * @property string|null $mime
 * @property int $bytes
 * @property string|null $checksum
 * @property string|null $run_id
 * @property string|null $step_id
 * @property string|null $message_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ArtifactModel extends Model
{
    use DispatchesModelEvents;

    /** @use HasFactory<ArtifactFactory> */
    use HasFactory;

    use HasUlids;

    // Prunable, not MassPrunable: mass pruning issues a bulk delete without
    // hydrating models, so pruning() never fires and every stored object is
    // orphaned on the disk.
    use Prunable;

    protected $table = 'impex_artifacts';

    protected $fillable = [
        'disk',
        'path',
        'kind',
        'mime',
        'bytes',
        'checksum',
        'run_id',
        'step_id',
        'message_id',
        'expires_at',
    ];

    /**
     * Read the artifact's contents from its disk.
     */
    public function contents(): ?string
    {
        return Storage::disk($this->disk)->get($this->path);
    }

    /**
     * @return Builder<ArtifactModel>
     */
    public function prunable(): Builder
    {
        return $this->newQuery()->whereNotNull('expires_at')->where('expires_at', '<=', Carbon::now());
    }

    /**
     * Delete the backing object when the record is pruned.
     */
    protected function pruning(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }

    /**
     * Prune a thousand at a time: one bulk delete per disk for the objects,
     * then one for the rows, instead of a delete and a round trip to the
     * disk per artifact. Objects go first, so a failure leaves rows to retry
     * rather than objects nobody points at.
     */
    public function pruneAll(int $chunkSize = 1000): int
    {
        $total = 0;

        do {
            $artifacts = $this->prunable()->orderBy('id')->limit($chunkSize)->get(['id', 'disk', 'path']);

            foreach ($artifacts->groupBy('disk') as $disk => $onDisk) {
                Storage::disk((string) $disk)->delete($onDisk->pluck('path')->map(fn (mixed $path): string => (string) $path)->all());
            }

            $total += $artifacts->isEmpty() ? 0 : (int) self::query()->whereKey($artifacts->modelKeys())->toBase()->delete();
        } while ($artifacts->count() === $chunkSize);

        return $total;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ArtifactKind::class,
            'bytes' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ArtifactFactory
    {
        return ArtifactFactory::new();
    }
}
