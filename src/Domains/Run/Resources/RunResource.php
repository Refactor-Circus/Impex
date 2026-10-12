<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Run\Enums\ChildClosePolicy;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * @mixin RunModel
 */
final class RunResource extends JsonResource
{
    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'flow' => $schema->string()->required(),
            'status' => $schema->string()->enum(array_column(RunStatus::cases(), 'value'))->required(),
            'trigger' => $schema->string()->enum(array_column(RunTrigger::cases(), 'value'))->required(),
            'version' => $schema->string()->nullable()->required(),
            'idempotency_key' => $schema->string()->nullable()->required(),
            'tags' => $schema->object()->nullable()->required(),
            'error' => $schema->object()->nullable()->required(),
            'parent_run_id' => $schema->string()->nullable()->required(),
            'close_policy' => $schema->string()->enum([...array_column(ChildClosePolicy::cases(), 'value'), null])->nullable()->description('How a child run closes with its parent.')->required(),
            'expires_at' => $schema->string()->nullable()->required(),
            'owners' => $schema->array()->items($schema->object(RunOwnerResource::schema($schema)))->description('When loaded.'),
            'steps' => $schema->array()->items($schema->object(RunStepResource::schema($schema)))->description('The forward steps, when loaded.'),
            'started_at' => $schema->string()->nullable()->required(),
            'finished_at' => $schema->string()->nullable()->required(),
            'created_at' => $schema->string()->nullable()->required(),
            'updated_at' => $schema->string()->nullable()->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flow' => $this->flow,
            'status' => $this->status->value,
            'trigger' => $this->trigger->value,
            'version' => $this->flow_version,
            'idempotency_key' => $this->idempotency_key,
            'tags' => $this->tags,
            'error' => $this->error,
            'parent_run_id' => $this->parent_run_id,
            'close_policy' => $this->close_policy?->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'owners' => RunOwnerResource::collection($this->whenLoaded('owners')),
            'steps' => RunStepResource::collection($this->whenLoaded('forwardSteps')),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
