<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Channel\Enums\BodyPolicy;
use RefactorCircus\Impex\Domains\Channel\Enums\ChannelStatus;
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Data\Missing;

/**
 * Input for UpdateChannelAction: the channel settings to change. Fields left
 * out are kept.
 */
final readonly class UpdateChannelData extends Data
{
    /**
     * @param  array<string, mixed>|Missing  $options
     * @param  array<string, mixed>|Missing  $credentials
     */
    public function __construct(
        public string|Missing $transport = new Missing,
        public ChannelStatus|Missing $status = new Missing,
        public BodyPolicy|Missing $bodyPolicy = new Missing,
        public array|Missing $options = new Missing,
        public array|Missing $credentials = new Missing,
    ) {}

    /**
     * The name and direction are fixed: ledger rows carry the name, and a
     * channel turned around would leave its history describing the wrong way.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'transport' => ['sometimes', 'string', 'max:32'],
            'status' => ['sometimes', Rule::enum(ChannelStatus::class)],
            'body_policy' => ['sometimes', Rule::enum(BodyPolicy::class)],
            'options' => ['sometimes', 'array'],
            'credentials' => ['sometimes', 'array'],
        ];
    }
}
