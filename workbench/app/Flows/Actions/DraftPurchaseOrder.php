<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

/**
 * Drafts a purchase order awaiting approval.
 */
final class DraftPurchaseOrder
{
    /**
     * @return array{order: string, supplier: string, amount: int, currency: string, lines: int}
     */
    public function execute(string $order, string $supplier, int $amount): array
    {
        return [
            'order' => $order,
            'supplier' => $supplier,
            'amount' => $amount,
            'currency' => 'USD',
            'lines' => 1 + crc32($order) % 5,
        ];
    }
}
