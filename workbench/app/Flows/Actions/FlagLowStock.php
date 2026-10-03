<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

/**
 * Lists the SKUs the feed reports as running low.
 */
final class FlagLowStock
{
    /**
     * @param  array<int, array{sku: string, quantity: int}>  $items
     * @return array<int, string>
     */
    public function execute(array $items): array
    {
        $low = array_filter($items, fn (array $item): bool => $item['quantity'] < 10);

        return array_values(array_column($low, 'sku'));
    }
}
