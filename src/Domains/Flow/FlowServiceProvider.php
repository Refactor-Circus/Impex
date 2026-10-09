<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow;

use Illuminate\Console\Scheduling\Schedule;
use RefactorCircus\Impex\Domains\Flow\Console\Commands\RunFlowCommand;
use RefactorCircus\Impex\Domains\Flow\Services\FlowRegistry;
use RefactorCircus\Keystone\Support\ServiceProvider;

class FlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FlowRegistry::class);
    }

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/routes.php');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([RunFlowCommand::class]);

        $this->registerSchedule();
    }

    /**
     * Register each flow that declares a schedule.
     */
    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $registry = $this->app->make(FlowRegistry::class);

            foreach (array_keys($registry->all()) as $slug) {
                $cron = $registry->schedule($slug);

                if ($cron === null) {
                    continue;
                }

                $schedule->command(RunFlowCommand::class, [$slug, '--trigger=schedule'])->cron($cron);
            }
        });
    }
}
