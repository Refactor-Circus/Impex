<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Artifact\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RefactorCircus\Impex\Domains\Artifact\Enums\ArtifactKind;
use RefactorCircus\Impex\Domains\Artifact\Exceptions\PayloadException;
use RefactorCircus\Impex\Domains\Artifact\Models\ArtifactModel;
use Throwable;

/**
 * Moves values between the database and the artifact disk.
 *
 * Queue messages carry ULIDs only, never payloads: anything serializing above
 * the inline threshold is written to a disk and referenced by artifact id, so
 * a job body can never approach SQS's 256KB message limit.
 */
final class PayloadStore
{
    /**
     * Decoded artifact values by id, least recently used first. An artifact
     * is written once and never changed, so a replay that reads the same
     * result on every drive reads it from disk once per process.
     *
     * @var array<string, array{value: mixed, bytes: int}>
     */
    private array $decoded = [];

    private int $decodedBytes = 0;

    public function __construct(
        private readonly Config $config,
        private readonly FilesystemFactory $filesystem,
    ) {}

    /**
     * Store a value, inlining it when small enough.
     *
     * @param  array<string, string|null>  $links
     * @return array{inline: array<string, mixed>|null, artifact_id: string|null}
     */
    public function put(mixed $value, ArtifactKind $kind = ArtifactKind::Payload, array $links = []): array
    {
        $encoded = $this->encode($value);

        if (strlen($encoded) <= $this->threshold()) {
            return ['inline' => ['value' => $value], 'artifact_id' => null];
        }

        $disk = $this->disk();
        $path = $this->path($kind);

        $this->filesystem->disk($disk)->put($path, $encoded);

        $artifact = ArtifactModel::query()->create([
            'disk' => $disk,
            'path' => $path,
            'kind' => $kind,
            'mime' => 'application/json',
            'bytes' => strlen($encoded),
            'checksum' => hash('sha256', $encoded),
            'run_id' => $links['run_id'] ?? null,
            'step_id' => $links['step_id'] ?? null,
            'message_id' => $links['message_id'] ?? null,
            'expires_at' => $this->expiry(),
        ]);

        return ['inline' => null, 'artifact_id' => $artifact->getKey()];
    }

    /**
     * Read a value back, from the inline column or the artifact disk.
     *
     * @param  array<string, mixed>|null  $inline
     */
    public function get(?array $inline, ?string $artifactId): mixed
    {
        if ($artifactId !== null) {
            return $this->artifact($artifactId);
        }

        if ($inline === null) {
            return null;
        }

        return $inline['value'] ?? null;
    }

    /**
     * Forget memoised artifact values: one, or all of them.
     */
    public function forget(?string $artifactId = null): void
    {
        if ($artifactId === null) {
            $this->decoded = [];
            $this->decodedBytes = 0;

            return;
        }

        if (isset($this->decoded[$artifactId])) {
            $this->decodedBytes -= $this->decoded[$artifactId]['bytes'];
            unset($this->decoded[$artifactId]);
        }
    }

    private function artifact(string $artifactId): mixed
    {
        if (isset($this->decoded[$artifactId])) {
            $entry = $this->decoded[$artifactId];

            // Move it to the most recently used end.
            unset($this->decoded[$artifactId]);
            $this->decoded[$artifactId] = $entry;

            return $entry['value'];
        }

        $artifact = ArtifactModel::query()->find($artifactId);

        if (! $artifact instanceof ArtifactModel) {
            throw PayloadException::artifactMissing($artifactId);
        }

        $contents = $artifact->contents();

        if ($contents === null) {
            throw PayloadException::artifactMissing($artifactId);
        }

        $value = $this->decode($contents);

        $this->remember($artifactId, $value, strlen($contents));

        return $value;
    }

    private function remember(string $artifactId, mixed $value, int $bytes): void
    {
        /** @var int $cap */
        $cap = $this->config->get('impex.artifacts.memo_bytes', 16777216);

        if ($bytes > $cap) {
            return;
        }

        while ($this->decoded !== [] && $this->decodedBytes + $bytes > $cap) {
            $this->forget((string) array_key_first($this->decoded));
        }

        $this->decoded[$artifactId] = ['value' => $value, 'bytes' => $bytes];
        $this->decodedBytes += $bytes;
    }

    /**
     * The byte size at which a value stops being stored inline.
     */
    public function threshold(): int
    {
        /** @var int $threshold */
        $threshold = $this->config->get('impex.artifacts.inline_threshold', 65536);

        return $threshold;
    }

    private function encode(mixed $value): string
    {
        try {
            return json_encode(['value' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            throw PayloadException::notSerializable($e);
        }
    }

    private function decode(string $encoded): mixed
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        return $decoded['value'] ?? null;
    }

    private function disk(): string
    {
        /** @var string|null $disk */
        $disk = $this->config->get('impex.artifacts.disk');

        /** @var string $default */
        $default = $this->config->get('filesystems.default', 'local');

        return $disk ?? $default;
    }

    private function path(ArtifactKind $kind): string
    {
        /** @var string $prefix */
        $prefix = $this->config->get('impex.artifacts.path', 'impex');

        return sprintf(
            '%s/%s/%s.json',
            trim($prefix, '/'),
            $kind->value,
            (string) Str::ulid(),
        );
    }

    private function expiry(): ?Carbon
    {
        /** @var int|null $days */
        $days = $this->config->get('impex.retention.artifacts_days');

        return $days === null ? null : Carbon::now()->addDays($days);
    }
}
