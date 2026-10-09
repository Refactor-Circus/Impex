<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RefactorCircus\Impex\Database\Factories\SubscriberFactory;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriberStatus;
use RefactorCircus\Keystone\Models\Concerns\DispatchesModelEvents;

/**
 * Someone outside the application who receives its changes: a vendor, a
 * marketplace, a partner's ERP. Authenticates as an OAuth client.
 *
 * @property string $id
 * @property string $name
 * @property string|null $client_id
 * @property SubscriberStatus $status
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class SubscriberModel extends Model
{
    use DispatchesModelEvents;

    /** @use HasFactory<SubscriberFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'impex_subscribers';

    protected $fillable = [
        'name',
        'client_id',
        'status',
        'owner_type',
        'owner_id',
        'metadata',
    ];

    /**
     * @return HasMany<SubscriptionModel, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SubscriptionModel::class, 'subscriber_id');
    }

    public function isOwnedBy(Model $owner): bool
    {
        return $this->owner_type === $owner->getMorphClass()
            && $this->owner_id === (string) $owner->getKey();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): SubscriberFactory
    {
        return SubscriberFactory::new();
    }
}
