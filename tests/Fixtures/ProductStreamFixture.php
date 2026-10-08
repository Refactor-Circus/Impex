<?php

declare(strict_types=1);

namespace JayI\Impex\Tests\Fixtures;

use JayI\Impex\Domains\Subscription\Contracts\SubscriptionMatcher;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Domains\Subscription\Support\AbstractStream;

/**
 * A snapshot stream over FakeCatalog: pricing is the price, content the
 * name, and subscriptions filter by category path prefix.
 */
final class ProductStreamFixture extends AbstractStream
{
    public function key(): string
    {
        return 'fixture.products';
    }

    public function topics(): array
    {
        return ['pricing', 'content'];
    }

    public function snapshots(array $keys): array
    {
        FakeCatalog::$loads++;

        $snapshots = [];

        foreach ($keys as $key) {
            $snapshots[$key] = FakeCatalog::$products[$key] ?? null;
        }

        return $snapshots;
    }

    public function slice(array $snapshot): array
    {
        return ['pricing' => $snapshot['price'] ?? null, 'content' => $snapshot['name'] ?? null];
    }

    public function filterRules(): array
    {
        return ['filter.category' => ['required', 'string']];
    }

    public function matcher(): SubscriptionMatcher
    {
        return new class implements SubscriptionMatcher
        {
            public function match(array $snapshots, array $subscriptions): array
            {
                $matches = [];

                foreach ($subscriptions as $subscription) {
                    $prefix = (string) ($subscription->filter['category'] ?? '');

                    foreach ($snapshots as $key => $snapshot) {
                        if (str_starts_with((string) ($snapshot['category'] ?? ''), $prefix)) {
                            $matches[$subscription->id][] = (string) $key;
                        }
                    }
                }

                return $matches;
            }
        };
    }

    public function export(SubscriptionModel $subscription, mixed $handle): void
    {
        foreach (FakeCatalog::$products as $sku => $product) {
            fwrite($handle, json_encode(['subject' => $sku, 'data' => $product], JSON_THROW_ON_ERROR)."\n");
        }
    }
}
