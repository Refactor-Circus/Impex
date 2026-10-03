<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message;

use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Message\Services\ChannelRegistry;
use JayI\Impex\Domains\Message\Services\MessageRecorder;
use JayI\Impex\Domains\Message\Services\OutboundRecorder;
use JayI\Impex\Support\ServiceProvider;

class MessageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelRegistry::class);

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
}
