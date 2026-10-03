<?php

declare(strict_types=1);

namespace JayI\Impex;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use JayI\Atrium\Facades\Atrium;
use JayI\Impex\Atrium\ImpexPlugin;
use JayI\Impex\Atrium\ScreenAccess;
use JayI\Impex\Console\Commands\PruneCommand;
use JayI\Impex\Cortex\CortexIntegration;
use JayI\Impex\Domains\DomainServiceProvider;
use JayI\Impex\Mcp\ImpexServer;
use JayI\Impex\Support\Locks;
use Laravel\Mcp\Facades\Mcp;

class ImpexServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/impex.php', 'impex');

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
        $this->app->make(CortexIntegration::class)->register();

        $this->registerPolicies();

        $this->registerAtriumPlugin();

        $this->registerMcpServer();

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

    /**
     * Register each model's policy from `impex.policies`, so an application
     * swaps one by pointing its model at another class there.
     */
    private function registerPolicies(): void
    {
        /** @var array<class-string, class-string> $policies */
        $policies = $this->app->make('config')->get('impex.policies', []);

        foreach ($policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Register Impex with the Atrium dashboard.
     *
     * Atrium discovers the plugin from composer.json, so this only honours the
     * config switch that turns the dashboard surface off, and adds the
     * utilities Impex's screens use that Atrium's stylesheet lacks.
     */
    private function registerAtriumPlugin(): void
    {
        if (! class_exists(Atrium::class) || $this->app->make('config')->get('impex.ui.enabled') !== true) {
            return;
        }

        Atrium::plugin(ImpexPlugin::class);

        Atrium::css((string) file_get_contents(__DIR__.'/../resources/css/atrium.css'), 'impex');
    }

    private function registerMcpServer(): void
    {
        if (! class_exists(Mcp::class)) {
            return;
        }

        $config = $this->app->make('config');

        if ($config->get('impex.mcp.web.enabled') === true) {
            /** @var array<int, string> $middleware */
            $middleware = $config->get('impex.mcp.web.middleware', []);

            Mcp::web((string) $config->get('impex.mcp.web.route'), ImpexServer::class)
                ->middleware($middleware);
        }

        if ($config->get('impex.mcp.local.enabled') === true) {
            Mcp::local((string) $config->get('impex.mcp.local.handle'), ImpexServer::class);
        }
    }
}
