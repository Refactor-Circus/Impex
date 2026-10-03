<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

/**
 * Loads one day's orders. Generated from the date, so a day always has the
 * same orders.
 */
final class FetchOrders
{
    /**
     * @return array<int, array{number: string, customer: string, total: float}>
     */
    public function execute(string $date): array
    {
        $seed = crc32($date);
        $orders = [];

        for ($i = 0; $i < 6 + $seed % 9; $i++) {
            $orders[] = [
                'number' => sprintf('SO-%s-%03d', str_replace('-', '', $date), $i + 1),
                'customer' => sprintf('CUST-%04d', 1000 + ($seed + $i * 37) % 140),
                'total' => round(((($seed >> ($i % 16)) % 90000) + 1500) / 100, 2),
            ];
        }

        return $orders;
    }
}
