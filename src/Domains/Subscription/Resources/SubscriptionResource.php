<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\StreamRegistry;
use RefactorCircus\Impex\Domains\Subscription\Support\TopicMask;

/**
 * A subscription. Never its signing secret: that is returned once, when the
 * subscription is created or the secret rotated.
 *
 * @mixin SubscriptionModel
 */
final class SubscriptionResource extends JsonResource
{
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
