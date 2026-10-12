<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Data\Missing;

/**
 * Input for UpdateSubscriptionAction: the settings to change. Fields left
 * out are kept. The stream is fixed; a different stream is a different
 * subscription.
 */
final readonly class UpdateSubscriptionData extends Data
{
    /**
     * @param  list<string>|Missing  $topics
     * @param  array<string, mixed>|Missing|null  $filter
     * @param  array<string, mixed>|Missing|null  $options
     * @param  SubscriptionStatus|Missing  $status  Active resumes a paused or
     *                                              disabled subscription;
     *                                              paused holds its events.
     * @param  int|Missing  $replayFrom  Deliver again from this event on.
     * @param  array<string, mixed>|Missing  $endpoint  A new `url` or `to`.
     */
    public function __construct(
        public array|Missing $topics = new Missing,
        public string|Missing $format = new Missing,
        public array|Missing|null $filter = new Missing,
        public array|Missing|null $options = new Missing,
        public SubscriptionStatus|Missing $status = new Missing,
        public int|Missing $replayFrom = new Missing,
        public array|Missing $endpoint = new Missing,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'topics' => ['sometimes', 'array'],
            'topics.*' => ['string', 'max:64'],
            'format' => ['sometimes', 'string', 'max:32'],
            'filter' => ['sometimes', 'nullable', 'array'],
            'options' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', Rule::in([SubscriptionStatus::Active->value, SubscriptionStatus::Paused->value])],
            'replay_from' => ['sometimes', 'integer', 'min:1'],
            'endpoint' => ['sometimes', 'array'],
            'endpoint.url' => ['sometimes', 'url', 'max:2048'],
            'endpoint.to' => ['sometimes', 'string', 'max:1024'],
        ];
    }
}
