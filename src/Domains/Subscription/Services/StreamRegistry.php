<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use RefactorCircus\Impex\Domains\Subscription\Contracts\Stream;
use RefactorCircus\Impex\Domains\Subscription\Exceptions\SubscriptionException;
use RefactorCircus\Impex\Domains\Subscription\Support\TopicMask;

/**
 * The streams subscribers can follow, from `impex.streams` and from
 * packages registering their own at boot.
 */
final class StreamRegistry
{
    /**
     * @var array<string, Stream>
     */
    private array $registered = [];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    /**
     * @param  Stream|class-string<Stream>  $stream
     */
    public function register(Stream|string $stream): Stream
    {
        $instance = $stream instanceof Stream ? $stream : $this->resolve($stream);

        if (count($instance->topics()) > TopicMask::MAX_TOPICS) {
            throw SubscriptionException::tooManyTopics($instance->key());
        }

        return $this->registered[$instance->key()] = $instance;
    }

    /**
     * @param  class-string<Stream>  $class
     */
    private function resolve(string $class): Stream
    {
        $instance = $this->container->make($class);

        return $instance instanceof Stream ? $instance : throw SubscriptionException::unknownStream($class);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function get(string $key): Stream
    {
        return $this->all()[$key] ?? throw SubscriptionException::unknownStream($key);
    }

    /**
     * @return array<string, Stream>
     */
    public function all(): array
    {
        /** @var array<int, class-string<Stream>> $configured */
        $configured = $this->config->get('impex.streams', []);

        foreach ($configured as $class) {
            if (! $this->isRegistered($class)) {
                $this->register($class);
            }
        }

        return $this->registered;
    }

    /**
     * @param  class-string<Stream>  $class
     */
    private function isRegistered(string $class): bool
    {
        foreach ($this->registered as $stream) {
            if ($stream instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
