<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Event;
use JayI\Foundation\Support\ServiceProvider;
use JayI\Impex\Domains\Channel\Events\ChannelDeletedEvent;
use JayI\Impex\Domains\Channel\Events\ChannelSavedEvent;
use JayI\Impex\Domains\Channel\Listeners\RecordSentMail;
use JayI\Impex\Domains\Channel\Mail\RecordingMailTransport;
use JayI\Impex\Domains\Channel\Services\ChannelRegistry;
use JayI\Impex\Domains\Channel\Services\ChannelSender;
use JayI\Impex\Domains\Channel\Services\TransportManager;
use JayI\Impex\Domains\Channel\Support\EndpointGuard;
use JayI\Impex\Domains\Message\Services\MessageRecorder;

class ChannelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelRegistry::class);

        $this->app->singleton(TransportManager::class);

        $this->app->singleton(ChannelSender::class);

        $this->app->singleton(EndpointGuard::class);
    }

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/routes.php');

        $this->registerMailTransport();

        Event::listen(MessageSent::class, RecordSentMail::class);

        // However a channel changes — an action, a seeder, tinker — this
        // process stops serving the old copy of it.
        Event::listen(
            [ChannelSavedEvent::class, ChannelDeletedEvent::class],
            function (): void {
                $this->app->make(ChannelRegistry::class)->flush();
            },
        );
    }

    /**
     * The `impex` mail transport: a mailer that records every mail it sends,
     * wrapping the mailer named in its `mailer` key.
     */
    private function registerMailTransport(): void
    {
        $this->callAfterResolving('mail.manager', function (MailManager $mail): void {
            $mail->extend('impex', function (array $config) use ($mail): RecordingMailTransport {
                /** @var string|null $inner */
                $inner = $config['mailer'] ?? null;

                /** @var string $channel */
                $channel = $config['channel'] ?? $this->app->make(Config::class)->get('impex.mail.channel', 'mail');

                return new RecordingMailTransport(
                    $mail->mailer($inner)->getSymfonyTransport(),
                    $this->app->make(MessageRecorder::class),
                    $channel,
                );
            });
        });
    }
}
