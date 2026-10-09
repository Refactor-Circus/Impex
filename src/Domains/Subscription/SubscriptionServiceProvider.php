<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use RefactorCircus\Foundation\Support\ServiceProvider;
use RefactorCircus\Impex\Domains\Subscription\Contracts\ResolvesSubscriber;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionDeletedEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionSavedEvent;
use RefactorCircus\Impex\Domains\Subscription\Http\Middleware\ResolveSubscriber;
use RefactorCircus\Impex\Domains\Subscription\Services\Detector;
use RefactorCircus\Impex\Domains\Subscription\Services\Dispatcher;
use RefactorCircus\Impex\Domains\Subscription\Services\EventReader;
use RefactorCircus\Impex\Domains\Subscription\Services\Exporter;
use RefactorCircus\Impex\Domains\Subscription\Services\FanOut;
use RefactorCircus\Impex\Domains\Subscription\Services\StreamRegistry;
use RefactorCircus\Impex\Domains\Subscription\Services\Streams;
use RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionJobs;
use RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionSweeper;
use RefactorCircus\Impex\Domains\Subscription\Support\Backoff;
use RefactorCircus\Impex\Domains\Subscription\Support\OAuthClientResolver;
use RefactorCircus\Impex\Domains\Subscription\Support\SubscriptionSettings;

class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            StreamRegistry::class,
            Streams::class,
            SubscriptionJobs::class,
            Detector::class,
            FanOut::class,
            EventReader::class,
            Dispatcher::class,
            Exporter::class,
            SubscriptionSweeper::class,
            SubscriptionSettings::class,
            Backoff::class,
        ] as $service) {
            $this->app->singleton($service);
        }

        // Bind your own to place subscribers some other way, such as by the
        // signed-in user rather than the OAuth client.
        $this->app->singleton(ResolvesSubscriber::class, function (): ResolvesSubscriber {
            /** @var class-string<ResolvesSubscriber> $resolver */
            $resolver = $this->app->make(Repository::class)->get('impex.subscriptions.resolver', OAuthClientResolver::class);

            return $this->app->make($resolver);
        });
    }

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/routes.php');

        $this->loadSubscriberRoutesFrom(__DIR__.'/routes/subscriber.php');

        // A changed subscription takes effect on the next chunk detected in
        // this process, rather than once the cached copy ages out.
        Event::listen([SubscriptionSavedEvent::class, SubscriptionDeletedEvent::class], function (): void {
            $this->app->make(FanOut::class)->flush();
        });
    }

    /**
     * The subscriber API: its own stack, because a subscriber is an OAuth
     * client, not an operator, and its own prefix under the API's.
     */
    private function loadSubscriberRoutesFrom(string $path): void
    {
        $config = $this->app->make(Repository::class);

        if ($config->get('impex.routes.enabled') !== true || $this->routesAreCached()) {
            return;
        }

        /** @var string $prefix */
        $prefix = $config->get('impex.routes.prefix');

        /** @var array<int, string> $middleware */
        $middleware = $config->get('impex.routes.subscriber_middleware', ['api']);

        Route::prefix(trim($prefix, '/').'/subscriber')
            ->middleware([...$middleware, ResolveSubscriber::class])
            ->name('impex.subscriber.')
            ->group($path);
    }
}
