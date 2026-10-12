<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Run\Enums\StepPhase;
use RefactorCircus\Impex\Domains\Run\Enums\StepStatus;
use RefactorCircus\Impex\Domains\Run\Enums\StepType;
use RefactorCircus\Impex\Domains\Run\Models\RunStepModel;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * @mixin RunStepModel
 */
final class RunStepResource extends JsonResource
{
    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'phase' => $schema->string()->enum(array_column(StepPhase::cases(), 'value'))->required(),
            'sequence' => $schema->integer()->required(),
            'type' => $schema->string()->enum(array_column(StepType::cases(), 'value'))->required(),
            'name' => $schema->string()->required(),
            'status' => $schema->string()->enum(array_column(StepStatus::cases(), 'value'))->required(),
            'attempts' => $schema->integer()->required(),
            'max_attempts' => $schema->integer()->required(),
            'resumptions' => $schema->integer()->required(),
            'undone' => $schema->boolean()->required(),
            'undoes_sequence' => $schema->integer()->nullable()->required(),
            'unit_id' => $schema->string()->nullable()->required(),
            'expires_at' => $schema->string()->nullable()->required(),
            'error' => $schema->object()->nullable()->required(),
            'has_result' => $schema->boolean()->required(),
            'result_artifact_id' => $schema->string()->nullable()->description('Set when the result is on the artifact disk.')->required(),
            'started_at' => $schema->string()->nullable()->required(),
            'completed_at' => $schema->string()->nullable()->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phase' => $this->phase->value,
            'sequence' => $this->sequence,
            'type' => $this->type->value,
            'name' => $this->name,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'max_attempts' => $this->max_attempts,
            'resumptions' => $this->resumptions,
            'undone' => $this->undone,
            'undoes_sequence' => $this->undoes_sequence,
            'unit_id' => $this->unit_id,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'error' => $this->error,
            // Payloads are deliberately not inlined: a step result can be
            // hundreds of megabytes on the artifact disk.
            'has_result' => $this->result !== null || $this->result_artifact_id !== null,
            'result_artifact_id' => $this->result_artifact_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
