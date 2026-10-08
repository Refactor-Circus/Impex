<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JayI\Foundation\Models\Concerns\DispatchesModelEvents;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Subscription\Enums\DeliveryStatus;

/**
 * One attempt to deliver a batch of events: which range, how it went, and
 * the ledger row recording what was sent.
 *
 * @property string $id
 * @property string $subscription_id
 * @property int $first_event_id
 * @property int $last_event_id
 * @property int $events
 * @property DeliveryStatus $status
 * @property string|null $message_id
 * @property int|null $status_code
 * @property int|null $duration_ms
 * @property array<string, mixed>|null $error
 * @property Carbon $attempted_at
 * @property-read SubscriptionModel $subscription
 */
final class DeliveryModel extends Model
{
    use DispatchesModelEvents;
    use HasUlids;

    // A delivery owns nothing outside its row; the ledger row it points at
    // prunes on its own schedule.
    use MassPrunable;

    public $timestamps = false;

    protected $table = 'impex_deliveries';

    protected $fillable = [
        'subscription_id',
        'first_event_id',
        'last_event_id',
        'events',
        'status',
        'message_id',
        'status_code',
        'duration_ms',
        'error',
        'attempted_at',
    ];

    /**
     * @return BelongsTo<SubscriptionModel, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionModel::class, 'subscription_id');
    }

    /**
     * @return BelongsTo<MessageModel, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(MessageModel::class, 'message_id');
    }

    /**
     * @return Builder<DeliveryModel>
     */
    public function prunable(): Builder
    {
        /** @var int $days */
        $days = config('impex.retention.deliveries_days', 30);

        return $this->newQuery()->where('attempted_at', '<=', Carbon::now()->subDays($days));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_event_id' => 'integer',
            'last_event_id' => 'integer',
            'events' => 'integer',
            'status' => DeliveryStatus::class,
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'error' => 'array',
            'attempted_at' => 'datetime',
        ];
    }
}
