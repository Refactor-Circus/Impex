<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Data;

use RefactorCircus\Keystone\Data\Data;

/**
 * Input for AttachRunOwnerAction: the owning model, by morph class and key,
 * and the role it holds.
 */
final readonly class AttachRunOwnerData extends Data
{
    public function __construct(
        public string $ownerType,
        public string $ownerId,
        public string $role,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'owner_type' => ['required', 'string', 'max:191'],
            'owner_id' => ['required', 'string', 'max:64'],
            'role' => ['required', 'string', 'max:32'],
        ];
    }
}
