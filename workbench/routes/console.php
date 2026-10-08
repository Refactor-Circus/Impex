<?php

use Illuminate\Support\Facades\Artisan;
use Workbench\App\Bench\SubscriptionBench;

// A benchmark of the subscription pipeline on synthetic data. Run it against
// the database you deploy on; SQLite numbers are a floor, not a forecast.
//
//     vendor/bin/testbench workbench:build && vendor/bin/testbench impex:bench --subjects=200000 --subscriptions=500
Artisan::command('impex:bench {--subjects=50000} {--subscriptions=200} {--changed=0.1}', function (): void {
    $rows = app(SubscriptionBench::class)->run(
        (int) $this->option('subjects'),
        (int) $this->option('subscriptions'),
        (float) $this->option('changed'),
    );

    $this->table(['Stage', 'Time', 'Throughput', 'Peak memory'], $rows);
})->purpose('Benchmark Impex subscriptions on synthetic data');
