<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Contracts;

use RefactorCircus\Impex\Domains\Subscription\Data\StreamEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * Turns events into what a subscriber receives.
 *
 * Runs at send time, against the subjects as they are now, so what a
 * subscriber gets is never older than the moment it was sent.
 */
interface Formatter
{
    /**
     * One entry per event, in order.
     *
     * @param  list<StreamEvent>  $events
     * @param  array<string, array<string, mixed>|null>  $snapshots  Current
     *                                                               state, by subject key.
     * @return list<array<string, mixed>>
     */
    public function format(Stream $stream, SubscriptionModel $subscription, array $events, array $snapshots): array;
}
