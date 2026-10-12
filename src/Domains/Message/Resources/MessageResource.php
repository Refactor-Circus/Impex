<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * @mixin MessageModel
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'run_id' => $schema->string()->nullable()->required(),
            'step_id' => $schema->string()->nullable()->required(),
            'direction' => $schema->string()->enum(array_column(Direction::cases(), 'value'))->required(),
            'channel' => $schema->string()->required(),
            'transport' => $schema->string()->required(),
            'endpoint' => $schema->string()->required(),
            'method' => $schema->string()->nullable()->required(),
            'status_code' => $schema->integer()->nullable()->required(),
            'headers' => $schema->object()->nullable()->required(),
            'bytes' => $schema->integer()->required(),
            'body_preview' => $schema->string()->nullable()->required(),
            'body_artifact_id' => $schema->string()->nullable()->description('Set when the body is on the artifact disk.')->required(),
            'body_encoding' => $schema->string()->required(),
            'body_sha256' => $schema->string()->nullable()->required(),
            'delivery_id' => $schema->string()->nullable()->required(),
            'signature_valid' => $schema->boolean()->nullable()->required(),
            'duration_ms' => $schema->integer()->nullable()->required(),
            'error' => $schema->object()->nullable()->required(),
            'occurred_at' => $schema->string()->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_id' => $this->run_id,
            'step_id' => $this->step_id,
            'direction' => $this->direction->value,
            'channel' => $this->channel,
            'transport' => $this->transport,
            'endpoint' => $this->endpoint,
            'method' => $this->method,
            'status_code' => $this->status_code,
            'headers' => $this->headers,
            'bytes' => $this->bytes,
            'body_preview' => $this->body_preview,
            'body_artifact_id' => $this->body_artifact_id,
            'body_encoding' => $this->body_encoding,
            'body_sha256' => $this->body_sha256,
            'delivery_id' => $this->delivery_id,
            'signature_valid' => $this->signature_valid,
            'duration_ms' => $this->duration_ms,
            'error' => $this->error,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
