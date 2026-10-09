<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal;

use RefactorCircus\Foundation\Support\ServiceProvider;
use RefactorCircus\Impex\Domains\Signal\Console\Commands\SignalCommand;
use RefactorCircus\Impex\Domains\Signal\Services\Waits;

class SignalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Waits::class);
    }

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([SignalCommand::class]);
        }
    }
}
