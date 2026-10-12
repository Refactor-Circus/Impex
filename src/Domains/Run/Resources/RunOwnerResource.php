<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * @mixin RunOwnerModel
 */
final class RunOwnerResource extends JsonResource
{
    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'owner_type' => $schema->string()->required(),
            'owner_id' => $schema->string()->required(),
            'role' => $schema->string()->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'role' => $this->role,
        ];
    }
}
