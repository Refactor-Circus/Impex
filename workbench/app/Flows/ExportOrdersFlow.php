<?php

declare(strict_types=1);

namespace Workbench\App\Flows;

use JayI\Impex\Flows\Flow;
use Workbench\App\Flows\Actions\DeliverExport;
use Workbench\App\Flows\Actions\FetchOrders;
use Workbench\App\Flows\Actions\RenderOrdersCsv;
use Workbench\App\Flows\Actions\SummariseOrders;

/**
 * Exports a day's orders as a CSV and drops it on a partner's SFTP server.
 */
final class ExportOrdersFlow extends Flow
{
    /**
     * @return array<string, mixed>
     */
    public function handle(?string $date = null): array
    {
        // The default window is "yesterday", read once and recorded, so a
        // replay after midnight still exports the same day.
        $date ??= $this->sideEffect('export-date', fn (): string => now()->subDay()->toDateString());

        $this->tag('date', $date);

        $orders = $this->action(FetchOrders::class, $date)->run();

        [$csv, $summary] = $this->parallel()
            ->action(RenderOrdersCsv::class, $orders)
            ->action(SummariseOrders::class, $orders)
            ->run();

        $delivery = $this->action(DeliverExport::class, (string) $this->context()->run->getKey(), "orders-{$date}.csv", $csv['csv'])
            ->tries(3)
            ->run();

        return ['date' => $date, ...$summary, 'path' => $delivery['path']];
    }
}
