<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Route;
use JayI\Foundation\Support\ServiceProvider;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Message\Services\MessageRecorder;
use JayI\Impex\Domains\Message\Services\OutboundRecorder;

class MessageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MessageRecorder::class);

        $this->app->singleton(OutboundRecorder::class);
    }

    public function boot(): void
    {
        $this->keepMorphAliases([
            'JayI\Impex\Models\Message' => MessageModel::class,
        ]);

        $this->loadApiRoutesFrom(__DIR__.'/routes.php');

        $this->loadChannelRoutesFrom(__DIR__.'/routes/channels.php');
    }

    /**
     * Load a routes file inside the inbound channel route group.
     *
     * Inbound channel endpoints authenticate per request with the channel's
     * signing secret, not with an operator's session or token, so they carry
     * their own stack (`impex.routes.channel_middleware`) rather than the JSON
     * API's. Putting them behind the operator middleware would lock out the
     * very senders they exist to receive - an upstream has no user and no role.
     */
    private function loadChannelRoutesFrom(string $path): void
    {
        $config = $this->app->make(Repository::class);

        if ($config->get('impex.routes.enabled') !== true || $this->routesAreCached()) {
            return;
        }

        /** @var string $prefix */
        $prefix = $config->get('impex.routes.prefix');

        /** @var array<int, string> $middleware */
        $middleware = $config->get('impex.routes.channel_middleware');

        Route::prefix($prefix)->middleware($middleware)->name('impex.')->group($path);
    }
}
