<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Formatters;

use JayI\Impex\Domains\Subscription\Contracts\Stream;
use JayI\Impex\Domains\Subscription\Data\StreamEvent;
use JayI\Impex\Domains\Subscription\Enums\EventKind;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Domains\Subscription\Support\TopicMask;

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
