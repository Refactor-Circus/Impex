<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Signal\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JayI\Impex\Domains\Signal\Models\SignalModel;

/**
 * @mixin SignalModel
 */
final class SignalResource extends JsonResource
{
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
