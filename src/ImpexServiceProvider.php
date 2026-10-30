<?php

declare(strict_types=1);

namespace RefactorCircus\Impex;

use Illuminate\Support\Facades\Blade;
use RefactorCircus\Impex\Atrium\ImpexPlugin;
use RefactorCircus\Impex\Atrium\ScreenAccess;
use RefactorCircus\Impex\Atrium\ScreenRegistrations;
use RefactorCircus\Impex\Console\Commands\PruneCommand;
use RefactorCircus\Impex\Domains\DomainServiceProvider;
use RefactorCircus\Impex\Mcp\ImpexServer;
use RefactorCircus\Impex\Support\Locks;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Support\PackageServiceProvider;

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

        // A missing record on an Impex route answers a plain "Not found.".
        $this->registerNotFoundResponses();

        // Before the Atrium registrations, whose labels are translated.
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'impex');

        $this->registerAtriumPlugin(ImpexPlugin::class);

        $this->registerAtriumScreens();

        $this->registerMcpServer();

        // GET impex/history: Impex's audit entries, while Impex audits
        // (refactor-circus/keen installed and impex.audit.enabled on).
        $this->loadHistoryRoutes();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'impex');

        // @impexCan('cancel', $run) ... @endimpexCan: shown exactly when the
        // action behind the control would be allowed.
        Blade::if('impexCan', ScreenAccess::allows(...));

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

    /**
     * Impex's sections on other packages' Atrium screens (the run link from
     * `impex.atrium.run_link.screens`), behind the same guard as
     * `registerAtriumPlugin()`: Atrium installed and `impex.ui.enabled`
     * exactly true. The class is named as a string, so no Atrium class
     * loads without Atrium.
     */
    private function registerAtriumScreens(): void
    {
        if (! class_exists('RefactorCircus\Atrium\Atrium') || config('impex.ui.enabled') !== true) {
            return;
        }

        ScreenRegistrations::register();
    }
}
