<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RefactorCircus\Impex\Domains\Subscription\Models\DeliveryModel;

/**
 * @mixin DeliveryModel
 */
final class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'first_event_id' => (string) $this->first_event_id,
            'last_event_id' => (string) $this->last_event_id,
            'events' => $this->events,
            'status' => $this->status->value,
            'message_id' => $this->message_id,
            'status_code' => $this->status_code,
            'duration_ms' => $this->duration_ms,
            'error' => $this->error,
            'attempted_at' => $this->attempted_at->toIso8601String(),
        ];
    }
}
