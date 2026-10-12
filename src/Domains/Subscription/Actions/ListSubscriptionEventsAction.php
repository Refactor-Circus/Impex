<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Contracts\Config\Repository as Config;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscriptionEventsData;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionEventsListedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionEventsListingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\EventReader;

final class ListSubscriptionEventsAction
{
    public function __construct(
        private readonly EventReader $reader,
        private readonly Config $config,
    ) {}

    /**
     * The feed: a page of the subscription's events after a cursor, in the
     * same shape a push delivers. Pass the returned cursor back as `after` to
     * read on. Reading never moves the subscription's own cursor, so a
     * subscriber can pull and be pushed to without either stepping on the
     * other.
     *
     * @return array{events: list<array<string, mixed>>, cursor: string, has_more: bool}
     */
    public function execute(SubscriptionModel $subscription, ListSubscriptionEventsData $data = new ListSubscriptionEventsData): array
    {
        $after = $data->after;

        /** @var int $max */
        $max = $this->config->get('impex.subscriptions.feed.max_limit', 1000);
        $limit = min($max, $data->limit);

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
