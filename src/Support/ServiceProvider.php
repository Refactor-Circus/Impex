<?php

declare(strict_types=1);

namespace JayI\Impex\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Base class for the Impex domain service providers.
 *
 * Every JSON API route shares one group - the configured prefix and
 * middleware, and the `impex.` name prefix - so each domain loads its routes
 * file through `loadApiRoutesFrom()` rather than building the group itself.
 * Nothing loads unless `impex.routes.enabled` is true.
 */
abstract class ServiceProvider extends BaseServiceProvider
{
    /**
     * Load a routes file inside the JSON API's operator route group.
     */
    protected function loadApiRoutesFrom(string $path): void
    {
        $this->loadImpexRoutesFrom($path, 'impex.routes.middleware');
    }

    /**
     * Load a routes file inside the inbound channel route group.
     *
     * Inbound channel endpoints authenticate per request with the channel's
     * signing secret, not with an operator's session or token, so they carry
     * their own stack (`impex.routes.channel_middleware`). Putting them behind
     * the operator middleware would lock out the very senders they exist to
     * receive - an upstream has no user and no role.
     */
    protected function loadChannelRoutesFrom(string $path): void
    {
        $this->loadImpexRoutesFrom($path, 'impex.routes.channel_middleware');
    }

    /**
     * Keep the class names models were stored under before they moved into
     * their domain, so any polymorphic `*_type` column, audit trail or other
     * record an application wrote with the old names still resolves - and new
     * records keep writing the same value.
     *
     * @param  array<string, class-string<Model>>  $map
     */
    protected function keepMorphAliases(array $map): void
    {
        Relation::morphMap($map);
    }

    private function loadImpexRoutesFrom(string $path, string $middlewareKey): void
    {
        $config = $this->app->make(Repository::class);

        if ($config->get('impex.routes.enabled') !== true || $this->routesAreCached()) {
            return;
        }

        /** @var string $prefix */
        $prefix = $config->get('impex.routes.prefix');

        /** @var array<int, string> $middleware */
        $middleware = $config->get($middlewareKey);

        Route::prefix($prefix)->middleware($middleware)->name('impex.')->group($path);
    }

    private function routesAreCached(): bool
    {
        return $this->app instanceof CachesRoutes && $this->app->routesAreCached();
    }
}
