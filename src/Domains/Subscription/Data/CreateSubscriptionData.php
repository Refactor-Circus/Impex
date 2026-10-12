<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Data;

use Illuminate\Validation\Rule;
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Data\Missing;

/**
 * Input for CreateSubscriptionAction: the stream, what of it to follow, and
 * where to push. Without an endpoint the subscription is read from its feed.
 */
final readonly class CreateSubscriptionData extends Data
{
    /**
     * @param  list<string>  $topics  Empty means every topic.
     * @param  array<string, mixed>|Missing|null  $filter  Which subjects, in the stream's filter shape.
     * @param  list<string>  $subjects  An explicit list of subject keys to follow instead.
     * @param  array<string, mixed>|null  $options
     * @param  array<string, mixed>|null  $endpoint  `url` for a webhook, or `transport: mail` and `to`.
     */
    public function __construct(
        public string $stream,
        public array $topics = [],
        public ?string $format = null,
        public array|Missing|null $filter = new Missing,
        public array $subjects = [],
        public ?array $options = null,
        public ?array $endpoint = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'stream' => ['required', 'string', 'max:191'],
            'topics' => ['sometimes', 'array'],
            'topics.*' => ['string', 'max:64'],
            'format' => ['sometimes', 'string', 'max:32'],
            'filter' => ['sometimes', 'nullable', 'array'],
            'subjects' => ['sometimes', 'array', 'max:10000'],
            'subjects.*' => ['string', 'max:191'],
            'options' => ['sometimes', 'array'],
            // Where to push. Leave it out for a feed-only subscription.
            'endpoint' => ['sometimes', 'nullable', 'array'],
            'endpoint.transport' => ['sometimes', Rule::in(['http', 'mail'])],
            'endpoint.url' => ['exclude_without:endpoint', 'required_without:endpoint.to', 'nullable', 'url', 'max:2048'],
            'endpoint.to' => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }
}
