<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Subscription\Enums\Selection;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\StreamRegistry;
use RefactorCircus\Impex\Domains\Subscription\Support\TopicMask;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * A subscription. Never its signing secret: that is returned once, when the
 * subscription is created or the secret rotated.
 *
 * @mixin SubscriptionModel
 */
final class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'subscriber_id' => $schema->string()->required(),
            'stream' => $schema->string()->required(),
            'topics' => $schema->array()->items($schema->string())->required(),
            'selection' => $schema->string()->enum(array_column(Selection::cases(), 'value'))->required(),
            'filter' => $schema->object()->nullable()->required(),
            'format' => $schema->string()->required(),
            'options' => $schema->object()->nullable()->required(),
            'status' => $schema->string()->enum(array_column(SubscriptionStatus::cases(), 'value'))->required(),
            'endpoint' => $schema->object([
                'channel' => $schema->string(),
                'transport' => $schema->string(),
                'url' => $schema->string(),
                'to' => $schema->string(),
            ])->nullable()->description('Where events are pushed; null for a feed-only subscription or when the channel is not loaded.')->required(),
            'cursor' => $schema->string()->description('The last event delivered.')->required(),
            'failures' => $schema->integer()->required(),
            'pending' => $schema->boolean()->required(),
            'paused_until' => $schema->string()->nullable()->required(),
            'disabled_at' => $schema->string()->nullable()->required(),
            'last_delivered_at' => $schema->string()->nullable()->required(),
            'last_error' => $schema->object()->nullable()->required(),
            'last_export_path' => $schema->string()->nullable()->required(),
            'last_export_at' => $schema->string()->nullable()->required(),
            'created_at' => $schema->string()->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $registry = app(StreamRegistry::class);
        $topics = $registry->has($this->stream) ? $registry->get($this->stream)->topics() : [];
        $channel = $this->relationLoaded('channel') ? $this->channel : null;

        return [
            'id' => $this->id,
            'subscriber_id' => $this->subscriber_id,
            'stream' => $this->stream,
            'topics' => TopicMask::names($topics, $this->topics),
            'selection' => $this->selection->value,
            'filter' => $this->filter,
            'format' => $this->format,
            'options' => $this->options,
            'status' => $this->status->value,
            'endpoint' => $channel instanceof ChannelModel ? array_filter([
                'channel' => $channel->name,
                'transport' => $channel->transport,
                'url' => $channel->options['url'] ?? null,
                'to' => $channel->options['to'] ?? null,
            ]) : null,
            'cursor' => (string) $this->cursor,
            'failures' => $this->failures,
            'pending' => $this->pending_at !== null,
            'paused_until' => $this->paused_until?->toIso8601String(),
            'disabled_at' => $this->disabled_at?->toIso8601String(),
            'last_delivered_at' => $this->last_delivered_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'last_export_path' => $this->last_export_path,
            'last_export_at' => $this->last_export_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
