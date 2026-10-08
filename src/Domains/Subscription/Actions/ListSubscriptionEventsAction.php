<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Actions;

use Illuminate\Contracts\Config\Repository as Config;
use JayI\Impex\Domains\Subscription\Events\SubscriptionEventsListedActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscriptionEventsListingActionEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Domains\Subscription\Services\EventReader;

final class ListSubscriptionEventsAction
{
    public function __construct(
        private readonly EventReader $reader,
        private readonly Config $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'after' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * The feed: a page of the subscription's events after a cursor, in the
     * same shape a push delivers. Pass the returned cursor back as `after` to
     * read on. Reading never moves the subscription's own cursor, so a
     * subscriber can pull and be pushed to without either stepping on the
     * other.
     *
     * @param  array<string, mixed>  $data
     * @return array{events: list<array<string, mixed>>, cursor: string, has_more: bool}
     */
    public function execute(SubscriptionModel $subscription, array $data = []): array
    {
        $after = (int) ($data['after'] ?? 0);

        /** @var int $max */
        $max = $this->config->get('impex.subscriptions.feed.max_limit', 1000);
        $limit = min($max, (int) ($data['limit'] ?? 100));

        SubscriptionEventsListingActionEvent::dispatch($subscription, $after);

        $raw = $this->reader->read($subscription, $after, $limit);
        $last = $raw === [] ? $after : $raw[count($raw) - 1]->id;

        $page = [
            'events' => $this->reader->format($subscription, $this->reader->collapse($subscription, $raw)),
            'cursor' => (string) $last,
            'has_more' => count($raw) === $limit,
        ];

        SubscriptionEventsListedActionEvent::dispatch($subscription, $page);

        return $page;
    }
}
