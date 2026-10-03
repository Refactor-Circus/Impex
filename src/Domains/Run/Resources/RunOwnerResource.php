<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;

/**
 * @mixin RunOwnerModel
 */
final class RunOwnerResource extends JsonResource
{
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
