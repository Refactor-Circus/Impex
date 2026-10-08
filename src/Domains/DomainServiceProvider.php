<?php

declare(strict_types=1);

namespace JayI\Impex\Domains;

use Illuminate\Support\ServiceProvider;
use JayI\Impex\Domains\Artifact\ArtifactServiceProvider;
use JayI\Impex\Domains\Batch\BatchServiceProvider;
use JayI\Impex\Domains\Channel\ChannelServiceProvider;
use JayI\Impex\Domains\Flow\FlowServiceProvider;
use JayI\Impex\Domains\Message\MessageServiceProvider;
use JayI\Impex\Domains\Run\RunServiceProvider;
use JayI\Impex\Domains\Signal\SignalServiceProvider;
use JayI\Impex\Domains\Subscription\SubscriptionServiceProvider;

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
