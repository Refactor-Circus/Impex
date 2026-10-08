<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Formatters;

use JayI\Impex\Domains\Subscription\Contracts\Formatter;
use JayI\Impex\Domains\Subscription\Contracts\Stream;
use JayI\Impex\Domains\Subscription\Data\StreamEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Domains\Subscription\Support\TopicMask;

/**
 * Which subjects changed, in which topics, and nothing else. For subscribers
 * who fetch the data themselves.
 */
class ThinFormatter implements Formatter
{
    public function format(Stream $stream, SubscriptionModel $subscription, array $events, array $snapshots): array
    {
        return array_map(fn (StreamEvent $event): array => $this->entry($stream, $subscription, $event), $events);
    }

    /**
     * @return array<string, mixed>
     */
    protected function entry(Stream $stream, SubscriptionModel $subscription, StreamEvent $event): array
    {
        return array_filter([
            'id' => (string) $event->id,
            'type' => $event->kind->value,
            'name' => $event->name,
            'subject' => $event->subjectKey,
            'topics' => TopicMask::names($stream->topics(), $event->topics & $subscription->topics),
            'occurred_at' => $event->occurredAt->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null);
    }
}
