<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Formatters;

use RefactorCircus\Impex\Domains\Subscription\Contracts\Stream;
use RefactorCircus\Impex\Domains\Subscription\Data\StreamEvent;
use RefactorCircus\Impex\Domains\Subscription\Enums\EventKind;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Support\TopicMask;

/**
 * Only the topics that changed and that the subscriber asked for. A price
 * change reaches a pricing subscriber as prices, not as the whole product.
 */
class SliceFormatter extends ThinFormatter
{
    public function format(Stream $stream, SubscriptionModel $subscription, array $events, array $snapshots): array
    {
        return array_map(function (StreamEvent $event) use ($stream, $subscription, $snapshots): array {
            $entry = $this->entry($stream, $subscription, $event);
            $snapshot = $snapshots[$event->subjectKey] ?? null;

            if ($event->kind === EventKind::Appended) {
                return [...$entry, 'data' => $event->payload];
            }

            if ($event->kind === EventKind::Removed || $snapshot === null) {
                return $entry;
            }

            $wanted = TopicMask::names($stream->topics(), $event->topics & $subscription->topics);

            return [...$entry, 'data' => array_intersect_key($stream->slice($snapshot), array_flip($wanted))];
        }, $events);
    }
}
