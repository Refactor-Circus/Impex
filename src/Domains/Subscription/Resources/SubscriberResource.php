<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

/**
 * @mixin SubscriberModel
 */
final class SubscriberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'client_id' => $this->client_id,
            'status' => $this->status->value,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'metadata' => $this->metadata,
            'subscriptions_count' => $this->whenCounted('subscriptions'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
