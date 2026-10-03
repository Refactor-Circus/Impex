<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

/**
 * The rollback for DraftPurchaseOrder.
 */
final class VoidPurchaseOrder
{
    /**
     * @return array{order: string, voided: bool}
     */
    public function execute(string $order): array
    {
        return ['order' => $order, 'voided' => true];
    }
}
