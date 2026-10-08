<?php

declare(strict_types=1);

namespace Workbench\App\Bench;

use JayI\Impex\Domains\Subscription\Contracts\SubscriptionMatcher;
use JayI\Impex\Domains\Subscription\Support\AbstractStream;

/**
 * A synthetic catalogue for the benchmark: SKU-{n} in one of fifty
 * categories, with a price and a name. Snapshots are computed rather than
 * loaded, so the numbers measure Impex's own cost, not a host's queries.
 */
final class BenchStream extends AbstractStream
{
    /**
     * Bumped per SKU to make it change.
     *
     * @var array<string, int>
     */
    public static array $generation = [];

    public function key(): string
    {
        return 'bench.products';
    }

    public function topics(): array
    {
        return ['pricing', 'content'];
    }

    public function snapshots(array $keys): array
    {
        $snapshots = [];

        foreach ($keys as $key) {
            $n = (int) substr($key, 4);
            $generation = self::$generation[$key] ?? 0;

            $snapshots[$key] = [
                'sku' => $key,
                'price' => round(10 + ($n % 500) / 10 + $generation, 2),
                'name' => 'Product '.$n,
                'category' => '/c'.($n % 50).'/',
            ];
        }

        return $snapshots;
    }

    public function slice(array $snapshot): array
    {
        return ['pricing' => $snapshot['price'] ?? null, 'content' => $snapshot['name'] ?? null];
    }

    public function matcher(): SubscriptionMatcher
    {
        return new class implements SubscriptionMatcher
        {
            public function match(array $snapshots, array $subscriptions): array
            {
                $byCategory = [];

                foreach ($subscriptions as $subscription) {
                    $byCategory[(string) ($subscription->filter['category'] ?? '')][] = $subscription->id;
                }

                $matches = [];

                foreach ($snapshots as $key => $snapshot) {
                    foreach ($byCategory[(string) ($snapshot['category'] ?? '')] ?? [] as $id) {
                        $matches[$id][] = (string) $key;
                    }
                }

                return $matches;
            }
        };
    }
}
