<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Subscription\Enums\DeliveryStatus;
use RefactorCircus\Impex\Domains\Subscription\Models\DeliveryModel;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * @mixin DeliveryModel
 */
final class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'subscription_id' => $schema->string()->required(),
            'first_event_id' => $schema->string()->required(),
            'last_event_id' => $schema->string()->required(),
            'events' => $schema->integer()->required(),
            'status' => $schema->string()->enum(array_column(DeliveryStatus::cases(), 'value'))->required(),
            'message_id' => $schema->string()->nullable()->required(),
            'status_code' => $schema->integer()->nullable()->required(),
            'duration_ms' => $schema->integer()->nullable()->required(),
            'error' => $schema->object()->nullable()->required(),
            'attempted_at' => $schema->string()->required(),
        ];
    }

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
