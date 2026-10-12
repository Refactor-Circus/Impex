<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriberStatus;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Keystone\Data\Data;

/**
 * Input for CreateSubscriberAction: who the subscriber is and the OAuth
 * client its systems authenticate as.
 */
final readonly class CreateSubscriberData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $name,
        public ?string $clientId = null,
        public SubscriberStatus $status = SubscriberStatus::Active,
        public ?array $metadata = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // The OAuth client the subscriber's systems authenticate as.
            'client_id' => ['sometimes', 'nullable', 'string', 'max:191', Rule::unique(SubscriberModel::class, 'client_id')],
            'status' => ['sometimes', Rule::enum(SubscriberStatus::class)],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
