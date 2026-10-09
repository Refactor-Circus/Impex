<?php

declare(strict_types=1);

namespace RefactorCircus\Impex;

use Illuminate\Support\Facades\Blade;
use RefactorCircus\Foundation\Packages\Package;
use RefactorCircus\Foundation\Support\PackageServiceProvider;
use RefactorCircus\Impex\Atrium\ImpexPlugin;
use RefactorCircus\Impex\Atrium\ScreenAccess;
use RefactorCircus\Impex\Console\Commands\PruneCommand;
use RefactorCircus\Impex\Domains\DomainServiceProvider;
use RefactorCircus\Impex\Mcp\ImpexServer;
use RefactorCircus\Impex\Support\Locks;

class ImpexServiceProvider extends PackageServiceProvider
{
    /**
     * Describe Impex to the suite's shared runtime. Calls are authorized
     * through the Gate unless `impex.authorization` turns that off.
     */
    protected function definition(): Package
    {
        return Package::make('impex', __NAMESPACE__)
            ->label('Impex')
            ->server(ImpexServer::class)
            ->authorization();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/impex.php', 'impex');

        $this->registerPackage();

        // Each domain registers its own services: the engine and its
        // collaborators (Run), flows, signals and timers, batches, the
        // message ledger and channels, and the artifact store.
        $this->app->register(DomainServiceProvider::class);

        $this->app->singleton(Locks::class);

        $this->app->singleton(Impex::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cortex is optional: agents get the Impex tools only when it is loaded.
        $this->registerCortex();

        $this->registerPolicies();

        $this->registerAtriumPlugin(ImpexPlugin::class);

        $this->registerMcpServer();

        // GET impex/history: Impex's audit entries, once refactor-circus/keen is installed.
        $this->loadHistoryRoutes();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'impex');

        // @impexCan('cancel', $run) ... @endimpexCan: shown exactly when the
        // action behind the control would be allowed.
        Blade::if('impexCan', ScreenAccess::allows(...));

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'impex');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/impex.php' => config_path('impex.php'),
        ], ['impex', 'impex-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/impex'),
        ], ['impex', 'impex-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/impex'),
        ], ['impex', 'impex-lang']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['impex', 'impex-migrations']);

        $this->commands([PruneCommand::class]);
    }
}
