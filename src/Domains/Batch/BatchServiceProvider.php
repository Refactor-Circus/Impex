<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Batch;

use RefactorCircus\Foundation\Support\ServiceProvider;
use RefactorCircus\Impex\Domains\Batch\Services\BatchRunner;

class BatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BatchRunner::class);
    }
}
