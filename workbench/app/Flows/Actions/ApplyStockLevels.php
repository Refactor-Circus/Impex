<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

/**
 * Applies a supplier's stock levels to the catalogue.
 */
final class ApplyStockLevels
{
    /**
     * @param  array<int, array{sku: string, quantity: int}>  $items
     * @return array{skus: int, units: int}
     */
    public function execute(string $supplier, array $items): array
    {
        return ['skus' => count($items), 'units' => array_sum(array_column($items, 'quantity'))];
    }
}
