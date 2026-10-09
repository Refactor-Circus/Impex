<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Batch;

use RefactorCircus\Impex\Domains\Batch\Services\BatchRunner;
use RefactorCircus\Keystone\Support\ServiceProvider;

class BatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BatchRunner::class);
    }
}
