<?php

declare(strict_types=1);

namespace Workbench\App\Flows;

use JayI\Impex\Flows\Flow;
use Workbench\App\Flows\Actions\ApplyStockLevels;
use Workbench\App\Flows\Actions\FlagLowStock;

/**
 * Applies a supplier's stock feed, delivered to the `supplier-feed` channel.
 */
final class SupplierStockSyncFlow extends Flow
{
    /**
     * @param  array{supplier?: string, items?: array<int, array{sku: string, quantity: int}>}  $feed
     * @return array<string, mixed>
     */
    public function handle(array $feed = []): array
    {
        $supplier = $feed['supplier'] ?? 'unknown';
        $items = $feed['items'] ?? [];

        $this->tag('supplier', $supplier);

        $applied = $this->action(ApplyStockLevels::class, $supplier, $items)->run();

        // A missed low-stock alert is not worth undoing the stock update for.
        $low = $this->optionalAction(FlagLowStock::class, $items)->run();

        return ['supplier' => $supplier, ...$applied, 'low_stock' => $low ?? []];
    }
}
