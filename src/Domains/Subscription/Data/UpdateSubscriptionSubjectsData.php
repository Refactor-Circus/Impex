<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Data;

use RefactorCircus\Keystone\Data\Data;

/**
 * Input for UpdateSubscriptionSubjectsAction: the subject keys to add to,
 * and to remove from, the subscription's explicit list.
 */
final readonly class UpdateSubscriptionSubjectsData extends Data
{
    /**
     * @param  list<string>  $add
     * @param  list<string>  $remove
     */
    public function __construct(
        public array $add = [],
        public array $remove = [],
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'add' => ['sometimes', 'array', 'max:10000'],
            'add.*' => ['string', 'max:191'],
            'remove' => ['sometimes', 'array', 'max:10000'],
            'remove.*' => ['string', 'max:191'],
        ];
    }
}
