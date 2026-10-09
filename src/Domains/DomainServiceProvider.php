<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains;

use Illuminate\Support\ServiceProvider;
use RefactorCircus\Impex\Domains\Artifact\ArtifactServiceProvider;
use RefactorCircus\Impex\Domains\Batch\BatchServiceProvider;
use RefactorCircus\Impex\Domains\Channel\ChannelServiceProvider;
use RefactorCircus\Impex\Domains\Flow\FlowServiceProvider;
use RefactorCircus\Impex\Domains\Message\MessageServiceProvider;
use RefactorCircus\Impex\Domains\Run\RunServiceProvider;
use RefactorCircus\Impex\Domains\Signal\SignalServiceProvider;
use RefactorCircus\Impex\Domains\Subscription\SubscriptionServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * The domain service providers.
     *
     * @var array<int, class-string<ServiceProvider>>
     */
    private array $providers = [
        ArtifactServiceProvider::class,
        BatchServiceProvider::class,
        ChannelServiceProvider::class,
        FlowServiceProvider::class,
        MessageServiceProvider::class,
        RunServiceProvider::class,
        SignalServiceProvider::class,
        SubscriptionServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->providers as $provider) {
            $this->app->register($provider);
        }
    }
}
