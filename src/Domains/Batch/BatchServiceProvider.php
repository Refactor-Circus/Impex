<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Batch;

use JayI\Impex\Domains\Batch\Models\BatchItemModel;
use JayI\Impex\Domains\Batch\Models\BatchModel;
use JayI\Impex\Domains\Batch\Services\BatchRunner;
use JayI\Impex\Support\ServiceProvider;

class BatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BatchRunner::class);
    }

    public function boot(): void
    {
        $this->keepMorphAliases([
            'JayI\Impex\Models\Batch' => BatchModel::class,
            'JayI\Impex\Models\BatchItem' => BatchItemModel::class,
        ]);
    }
}
