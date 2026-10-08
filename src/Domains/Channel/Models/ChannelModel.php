<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JayI\Foundation\Models\Concerns\DispatchesModelEvents;
use JayI\Impex\Database\Factories\ChannelFactory;
use JayI\Impex\Domains\Channel\Enums\BodyPolicy;
use JayI\Impex\Domains\Channel\Enums\ChannelStatus;
use JayI\Impex\Domains\Message\Enums\Direction;

/**
 * A channel created at runtime rather than in config: the webhook endpoint a
 * subscriber registers, or an integration an operator adds from the
 * dashboard.
 *
 * @property string $id
 * @property string $name
 * @property Direction $direction
 * @property string $transport
 * @property ChannelStatus $status
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $options
 * @property BodyPolicy $body_policy
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ChannelModel extends Model
{
    use DispatchesModelEvents;

    /** @use HasFactory<ChannelFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'impex_channels';

    protected $fillable = [
        'name',
        'direction',
        'transport',
        'status',
        'credentials',
        'options',
        'body_policy',
        'owner_type',
        'owner_id',
    ];

    /**
     * Secrets never leave the model by serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['credentials'];

    /**
     * Whether the model is one of the given owner's.
     */
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
            'direction' => Direction::class,
            'status' => ChannelStatus::class,
            'body_policy' => BodyPolicy::class,
            'credentials' => 'encrypted:array',
            'options' => 'array',
        ];
    }

    protected static function newFactory(): ChannelFactory
    {
        return ChannelFactory::new();
    }
}
