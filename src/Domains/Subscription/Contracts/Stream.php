<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Contracts;

use RefactorCircus\Impex\Domains\Subscription\Enums\StreamKind;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * A source of changes subscribers can follow: products, orders,
 * manufacturers. A package registers one and Impex does the rest — working
 * out what changed, who wants it, and getting it to them.
 *
 * Extend AbstractStream rather than implementing this from scratch; it
 * supplies the defaults a stream rarely needs to change.
 */
interface Stream
{
    /**
     * The stream's name, such as `showroom.products`. Stored on every event
     * and subscription, so it never changes.
     */
    public function key(): string;

    public function kind(): StreamKind;

    /**
     * What a subscriber can choose to hear about, such as pricing, assets
     * and content. The order is stored as bit positions, so append new
     * topics at the end and never reorder or remove one. At most 31.
     *
     * @return list<string>
     */
    public function topics(): array;

    /**
     * The current state of each subject, keyed by subject key; null for one
     * that no longer exists. Called with up to a chunk of keys at a time, so
     * load them in one query, not one each.
     *
     * @param  list<string>  $keys
     * @return array<string, array<string, mixed>|null>
     */
    public function snapshots(array $keys): array;

    /**
     * A snapshot divided by topic. Each topic's slice is hashed to tell
     * whether it changed, so keep slices deterministic: same state, same
     * slice, same key order.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function slice(array $snapshot): array;

    public function matcher(): SubscriptionMatcher;

    /**
     * Validation rules for a subscription's filter, keyed under `filter.`.
     *
     * @return array<string, mixed>
     */
    public function filterRules(): array;

    /**
     * The formats a subscription can ask for, by name.
     *
     * @return array<string, Formatter>
     */
    public function formats(): array;

    /**
     * Write every subject the subscription covers, one JSON object per line,
     * so a new subscriber starts from a file rather than from millions of
     * webhooks.
     *
     * @param  resource  $handle
     */
    public function export(SubscriptionModel $subscription, mixed $handle): void;
}
