<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriberStatus;
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Data\Missing;

/**
 * Input for UpdateSubscriberAction: the subscriber fields to change. Fields
 * left out are kept.
 */
final readonly class UpdateSubscriberData extends Data
{
    /**
     * @param  array<string, mixed>|Missing|null  $metadata
     */
    public function __construct(
        public string|Missing $name = new Missing,
        public string|Missing|null $clientId = new Missing,
        public SubscriberStatus|Missing $status = new Missing,
        public array|Missing|null $metadata = new Missing,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'client_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'status' => ['sometimes', Rule::enum(SubscriberStatus::class)],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
