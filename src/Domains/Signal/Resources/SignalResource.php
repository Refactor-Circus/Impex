<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Signal\Models\SignalModel;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * @mixin SignalModel
 */
final class SignalResource extends JsonResource
{
    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'run_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'delivered_at' => $schema->string()->required(),
            'consumed_at' => $schema->string()->nullable()->required(),
            'consumed_sequence' => $schema->integer()->nullable()->required(),
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
            'name' => $this->name,
            'delivered_at' => $this->delivered_at->toIso8601String(),
            'consumed_at' => $this->consumed_at?->toIso8601String(),
            'consumed_sequence' => $this->consumed_sequence,
        ];
    }
}
