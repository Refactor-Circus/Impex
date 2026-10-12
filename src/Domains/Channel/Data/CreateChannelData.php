<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Channel\Enums\BodyPolicy;
use RefactorCircus\Impex\Domains\Channel\Enums\ChannelStatus;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Keystone\Data\Data;

/**
 * Input for CreateChannelAction: the stored channel's name, direction and
 * transport, and its settings.
 */
final readonly class CreateChannelData extends Data
{
    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>|null  $credentials
     */
    public function __construct(
        public string $name,
        public Direction $direction,
        public string $transport,
        public ChannelStatus $status = ChannelStatus::Active,
        public BodyPolicy $bodyPolicy = BodyPolicy::All,
        public array $options = [],
        public ?array $credentials = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            // Lowercase, dotted or dashed: the name appears in URLs and on
            // every ledger row, so it is never renamed once created.
            'name' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9][a-z0-9._-]*$/', Rule::unique(ChannelModel::class, 'name')],
            'direction' => ['required', Rule::enum(Direction::class)],
            'transport' => ['required', 'string', 'max:32'],
            'status' => ['sometimes', Rule::enum(ChannelStatus::class)],
            'body_policy' => ['sometimes', Rule::enum(BodyPolicy::class)],
            'options' => ['sometimes', 'array'],
            'credentials' => ['sometimes', 'array'],
        ];
    }
}
