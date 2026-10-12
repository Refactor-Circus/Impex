<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Keystone\Data\Data;

/**
 * Input for ListChannelsAction: an optional direction to keep to.
 */
final readonly class ListChannelsData extends Data
{
    public function __construct(
        public ?Direction $direction = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'direction' => ['sometimes', Rule::enum(Direction::class)],
        ];
    }
}
