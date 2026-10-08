<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use JayI\Impex\Domains\Artifact\Enums\ArtifactKind;
use JayI\Impex\Domains\Artifact\Services\PayloadStore;
use JayI\Impex\Domains\Channel\Enums\BodyPolicy;
use JayI\Impex\Domains\Channel\Services\ChannelRegistry;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Events\MessageRecorded;
use JayI\Impex\Domains\Message\Models\MessageModel;

/**
 * Writes the ledger.
 *
 * Every payload that crosses the application boundary becomes a row here,
 * linked to the run, step or delivery that caused it. This is the hot path of
 * everything the application sends and receives, so a row is written with one
 * query-builder insert rather than through Eloquent: no model events fire per
 * row, only MessageRecorded.
 *
 * Bodies up to the artifact threshold are kept whole in the row; larger ones
 * go to the artifact disk, so a 40MB supplier feed does not land in a column.
 * Whether a body is kept at all is the channel's body policy; its size and
 * sha256 are kept regardless.
 */
final class MessageRecorder
{
    public function __construct(
        private readonly PayloadStore $payloads,
        private readonly Config $config,
        private readonly Events $events,
        private readonly ChannelRegistry $channels,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * Record one crossing.
     *
     * @param  array<string, mixed>|null  $headers
     * @param  array<string, mixed>|null  $error
     * @param  BodyPolicy|null  $policy  Null uses the channel's own.
     * @param  int|null  $bytes  The size of a body too large to pass in, such
     *                           as a streamed feed.
     */
    public function record(
        Direction $direction,
        string $channel,
        string $transport,
        string $endpoint,
        ?string $body = null,
        ?string $method = null,
        ?int $statusCode = null,
        ?array $headers = null,
        ?string $runId = null,
        ?string $stepId = null,
        ?bool $signatureValid = null,
        ?int $durationMs = null,
        ?array $error = null,
        ?string $idempotencyKey = null,
        ?BodyPolicy $policy = null,
        ?string $deliveryId = null,
        ?int $bytes = null,
    ): MessageModel {
        $body ??= '';
        $id = (new MessageModel)->newUniqueId();
        $now = Carbon::now();
        $failed = $error !== null || ($statusCode !== null && $statusCode >= 400);
        $keep = $body !== '' && ($policy ?? $this->channels->bodyPolicy($channel))->keeps($failed);

        $row = [
            'id' => $id,
            'run_id' => $runId,
            'step_id' => $stepId,
            'delivery_id' => $deliveryId,
            'direction' => $direction->value,
            'channel' => $channel,
            'transport' => $transport,
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            'headers' => $headers === null ? null : $this->json($headers),
            'body' => null,
            'body_artifact_id' => null,
            'body_preview' => null,
            'body_encoding' => 'utf8',
            'body_sha256' => $body === '' ? null : hash('sha256', $body),
            'bytes' => $bytes ?? strlen($body),
            'signature_valid' => $signatureValid,
            'duration_ms' => $durationMs,
            'error' => $error === null ? null : $this->json($error),
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($keep) {
            $row = [...$row, ...$this->storeBody($body, $id, $runId)];
        }

        $query = $this->db->table('impex_messages');

        // Scoped to the channel, because two suppliers may legitimately reuse
        // a key. insertOrIgnore leans on the unique index, so two concurrent
        // redeliveries cannot both write: the loser reads the winner's row
        // back instead of failing.
        if ($idempotencyKey !== null && $query->insertOrIgnore($row) === 0) {
            $existing = MessageModel::query()
                ->where('channel', $channel)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof MessageModel) {
                return $existing;
            }
        } elseif ($idempotencyKey === null) {
            $query->insert($row);
        }

        $this->events->dispatch(new MessageRecorded($id));

        return (new MessageModel)->newFromBuilder($row);
    }

    /**
     * Read a recorded body back. Null when the channel's policy did not keep it.
     */
    public function body(MessageModel $message): ?string
    {
        $body = $message->body;

        if ($body === null && $message->body_artifact_id !== null) {
            $stored = $this->payloads->get(null, $message->body_artifact_id);
            $body = is_string($stored) ? $stored : null;
        }

        if ($body === null) {
            return null;
        }

        if ($message->body_encoding === 'base64') {
            $decoded = base64_decode($body, true);

            return $decoded === false ? null : $decoded;
        }

        return $body;
    }

    /**
     * Keep only the headers the channel asked to store.
     *
     * Webhook headers routinely carry bearer tokens and signatures; storing
     * them wholesale would put credentials in a table the dashboard renders.
     *
     * @param  array<string, array<int, string|null>|string|null>  $headers
     * @param  array<int, string>  $keep
     * @return array<string, mixed>|null
     */
    public function filterHeaders(array $headers, array $keep): ?array
    {
        if ($keep === []) {
            return null;
        }

        if (in_array('*', $keep, true)) {
            return $headers;
        }

        $wanted = array_map(fn (string $header): string => strtolower($header), $keep);

        return array_intersect_key(
            array_change_key_case($headers),
            array_flip($wanted),
        );
    }

    /**
     * Where a kept body goes: whole in the row up to the artifact threshold,
     * to the artifact disk above it. A binary body is base64'd first — a text
     * column and the artifact's JSON envelope both need valid UTF-8.
     *
     * @return array<string, string|null>
     */
    private function storeBody(string $body, string $id, ?string $runId): array
    {
        $binary = ! mb_check_encoding($body, 'UTF-8');
        $stored = $binary ? base64_encode($body) : $body;

        $columns = [
            'body_encoding' => $binary ? 'base64' : 'utf8',
            'body_preview' => $binary ? null : $this->preview($body),
        ];

        if (strlen($stored) <= $this->payloads->threshold()) {
            return [...$columns, 'body' => $stored];
        }

        $artifact = $this->payloads->put($stored, ArtifactKind::Payload, ['run_id' => $runId, 'message_id' => $id]);

        return [...$columns, 'body_artifact_id' => $artifact['artifact_id']];
    }

    private function preview(string $body): string
    {
        /** @var int $length */
        $length = $this->config->get('impex.messages.preview_bytes', 2048);

        return mb_strcut($body, 0, $length);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function json(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
