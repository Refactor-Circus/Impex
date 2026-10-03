<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run;

use Illuminate\Console\Scheduling\Schedule;
use JayI\Impex\Domains\Run\Console\Commands\TickCommand;
use JayI\Impex\Domains\Run\Contracts\RollbackStrategy;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Domains\Run\Services\Children;
use JayI\Impex\Domains\Run\Services\Engine;
use JayI\Impex\Domains\Run\Services\EngineOptions;
use JayI\Impex\Domains\Run\Services\JobRouter;
use JayI\Impex\Domains\Run\Services\Rollbacks;
use JayI\Impex\Domains\Run\Services\StepWriter;
use JayI\Impex\Domains\Run\Services\Sweeper;
use JayI\Impex\Support\ServiceProvider;

class RunServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Each engine collaborator is resolved from the container, so an
        // application can bind its own without forking the package.
        $this->app->singleton(EngineOptions::class);

        $this->app->singleton(JobRouter::class);

        $this->app->singleton(StepWriter::class);

        $this->app->singleton(Children::class);

        $this->app->singleton(Sweeper::class);

        // Bind your own to change what a failed run unwinds, and in what order.
        $this->app->singleton(RollbackStrategy::class, Rollbacks::class);

        $this->app->singleton(Engine::class);
    }

    public function boot(): void
    {
        $this->keepMorphAliases([
            'JayI\Impex\Models\Run' => RunModel::class,
            'JayI\Impex\Models\RunOwner' => RunOwnerModel::class,
            'JayI\Impex\Models\RunStep' => RunStepModel::class,
        ]);

        $this->loadApiRoutesFrom(__DIR__.'/routes.php');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([TickCommand::class]);

        $this->registerSchedule();
    }

    /**
     * Sweep due timers every minute.
     *
     * The sweep is what makes waits longer than the queue's delay ceiling
     * possible, so it is not optional: without it, a run that sleeps for a day
     * never wakes.
     */
    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if ($this->app->make('config')->get('impex.timers.enabled', true) === false) {
                return;
            }

            $schedule->command(TickCommand::class)
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();
        });
    }
}
