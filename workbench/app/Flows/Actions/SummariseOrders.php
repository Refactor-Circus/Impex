<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

/**
 * Totals the exported orders for the run's result.
 */
final class SummariseOrders
{
    /**
     * @param  array<int, array{number: string, customer: string, total: float}>  $orders
     * @return array{orders: int, revenue: float}
     */
    public function execute(array $orders): array
    {
        return ['orders' => count($orders), 'revenue' => round(array_sum(array_column($orders, 'total')), 2)];
    }
}
