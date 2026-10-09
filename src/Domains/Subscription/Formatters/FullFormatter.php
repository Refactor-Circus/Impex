<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Formatters;

use RefactorCircus\Impex\Domains\Subscription\Contracts\Stream;
use RefactorCircus\Impex\Domains\Subscription\Data\StreamEvent;
use RefactorCircus\Impex\Domains\Subscription\Enums\EventKind;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * The whole subject as it is now, whatever changed.
 */
class FullFormatter extends ThinFormatter
{
    public function format(Stream $stream, SubscriptionModel $subscription, array $events, array $snapshots): array
    {
        return array_map(function (StreamEvent $event) use ($stream, $subscription, $snapshots): array {
            $entry = $this->entry($stream, $subscription, $event);

            return match ($event->kind) {
                EventKind::Removed => $entry,
                EventKind::Appended => [...$entry, 'data' => $event->payload],
                EventKind::Changed => [...$entry, 'data' => $snapshots[$event->subjectKey] ?? null],
            };
        }, $events);
    }
}
