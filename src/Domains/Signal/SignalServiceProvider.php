<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Signal;

use JayI\Foundation\Support\ServiceProvider;
use JayI\Impex\Domains\Signal\Console\Commands\SignalCommand;
use JayI\Impex\Domains\Signal\Models\SignalModel;
use JayI\Impex\Domains\Signal\Models\TimerModel;
use JayI\Impex\Domains\Signal\Services\Waits;

class SignalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Waits::class);
    }

    public function boot(): void
    {
        $this->keepMorphAliases([
            'JayI\Impex\Models\Signal' => SignalModel::class,
            'JayI\Impex\Models\Timer' => TimerModel::class,
        ]);

        $this->loadApiRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([SignalCommand::class]);
        }
    }
}
