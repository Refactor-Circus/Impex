<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriberStatus;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * @mixin SubscriberModel
 */
final class SubscriberResource extends JsonResource
{
    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'client_id' => $schema->string()->nullable()->description('The OAuth client it authenticates as.')->required(),
            'status' => $schema->string()->enum(array_column(SubscriberStatus::cases(), 'value'))->required(),
            'owner_type' => $schema->string()->nullable()->required(),
            'owner_id' => $schema->string()->nullable()->required(),
            'metadata' => $schema->object()->nullable()->required(),
            'subscriptions_count' => $schema->integer()->description('When counted.'),
            'created_at' => $schema->string()->required(),
        ];
    }

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
