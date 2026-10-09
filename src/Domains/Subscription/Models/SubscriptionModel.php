<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RefactorCircus\Foundation\Models\Concerns\DispatchesModelEvents;
use RefactorCircus\Impex\Database\Factories\SubscriptionFactory;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Subscription\Enums\Selection;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;

/**
 * A subscriber's interest in one stream: which subjects, which topics, in
 * what format, delivered through which channel.
 *
 * @property string $id
 * @property string $subscriber_id
 * @property string $stream
 * @property string|null $channel_id
 * @property int $topics
 * @property Selection $selection
 * @property array<string, mixed>|null $filter
 * @property string $format
 * @property array<string, mixed>|null $options
 * @property SubscriptionStatus $status
 * @property int $cursor
 * @property int $failures
 * @property Carbon|null $pending_at
 * @property Carbon|null $paused_until
 * @property Carbon|null $disabled_at
 * @property Carbon|null $last_delivered_at
 * @property array<string, mixed>|null $last_error
 * @property string|null $last_export_path
 * @property Carbon|null $last_export_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SubscriberModel $subscriber
 * @property-read ChannelModel|null $channel
 */
final class SubscriptionModel extends Model
{
    use DispatchesModelEvents;

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'impex_subscriptions';

    protected $fillable = [
        'subscriber_id',
        'stream',
        'channel_id',
        'topics',
        'selection',
        'filter',
        'format',
        'options',
        'status',
        'cursor',
        'failures',
        'pending_at',
        'paused_until',
        'disabled_at',
        'last_delivered_at',
        'last_error',
        'last_export_path',
        'last_export_at',
    ];

    /**
     * @return BelongsTo<SubscriberModel, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(SubscriberModel::class, 'subscriber_id');
    }

    /**
     * @return BelongsTo<ChannelModel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChannelModel::class, 'channel_id');
    }

    /**
     * @return HasMany<DeliveryModel, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(DeliveryModel::class, 'subscription_id');
    }

    /**
     * Whether events are pushed to it rather than pulled from the feed.
     */
    public function pushes(): bool
    {
        return $this->channel_id !== null;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topics' => 'integer',
            'selection' => Selection::class,
            'filter' => 'array',
            'options' => 'array',
            'status' => SubscriptionStatus::class,
            'cursor' => 'integer',
            'failures' => 'integer',
            'pending_at' => 'datetime',
            'paused_until' => 'datetime',
            'disabled_at' => 'datetime',
            'last_delivered_at' => 'datetime',
            'last_error' => 'array',
            'last_export_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }
}
