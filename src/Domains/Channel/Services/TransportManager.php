<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Services;

use Illuminate\Support\Manager;
use RefactorCircus\Impex\Domains\Channel\Contracts\Transport;
use RefactorCircus\Impex\Domains\Channel\Transports\FileTransport;
use RefactorCircus\Impex\Domains\Channel\Transports\HttpTransport;
use RefactorCircus\Impex\Domains\Channel\Transports\MailTransport;

/**
 * The transports a channel can name.
 *
 * Add one with extend(), say an SFTP client or a message bus:
 *
 *     Impex::transports()->extend('sqs', fn ($app) => $app->make(SqsTransport::class));
 *
 * @method Transport driver(string|null $driver = null)
 */
final class TransportManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'http';
    }

    /**
     * Whether a transport of this name can be resolved.
     */
    public function has(string $name): bool
    {
        return isset($this->customCreators[$name])
            || method_exists($this, 'create'.ucfirst($name).'Driver');
    }

    protected function createHttpDriver(): Transport
    {
        return $this->container->make(HttpTransport::class);
    }

    protected function createMailDriver(): Transport
    {
        return $this->container->make(MailTransport::class);
    }

    protected function createFileDriver(): Transport
    {
        return $this->container->make(FileTransport::class);
    }
}
