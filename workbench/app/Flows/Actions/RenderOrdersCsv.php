<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

/**
 * Renders the orders as the CSV the partner expects.
 */
final class RenderOrdersCsv
{
    /**
     * @param  array<int, array{number: string, customer: string, total: float}>  $orders
     * @return array{csv: string, rows: int}
     */
    public function execute(array $orders): array
    {
        $lines = ['order_number,customer,total'];

        foreach ($orders as $order) {
            $lines[] = sprintf('%s,%s,%.2f', $order['number'], $order['customer'], $order['total']);
        }

        return ['csv' => implode("\n", $lines)."\n", 'rows' => count($orders)];
    }
}
