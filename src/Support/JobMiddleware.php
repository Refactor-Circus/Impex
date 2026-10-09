<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;
use RefactorCircus\Impex\Contracts\JobMiddlewareFactory;

/**
 * The queue middleware `impex.jobs.middleware` gives each Impex job: those
 * under `*` for every job, then those under the job's class.
 *
 * An entry is a middleware class resolved from the container, a list of a
 * class and its constructor arguments, or a JobMiddlewareFactory that builds
 * middleware for the job in hand. Classes and arrays only, so the config
 * still caches.
 */
final class JobMiddleware
{
    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    /**
     * The middleware for a job, from the running application's config.
     *
     * @return array<int, object>
     */
    public static function resolve(object $job): array
    {
        $container = Container::getInstance();

        return $container->make(self::class)->for($job);
    }

    /**
     * @return array<int, object>
     */
    public function for(object $job): array
    {
        /** @var array<string, array<int, mixed>> $configured */
        $configured = $this->config->get('impex.jobs.middleware', []);

        $middleware = [];

        foreach ([...$configured['*'] ?? [], ...$configured[$job::class] ?? []] as $entry) {
            $instance = $this->make($entry);

            if ($instance instanceof JobMiddlewareFactory) {
                array_push($middleware, ...array_values($instance->middleware($job)));

                continue;
            }

            $middleware[] = $instance;
        }

        return $middleware;
    }

    private function make(mixed $entry): object
    {
        if (is_string($entry) && class_exists($entry)) {
            return $this->container->make($entry);
        }

        if (is_array($entry) && is_string($entry[0] ?? null) && class_exists($entry[0])) {
            $class = array_shift($entry);

            return new $class(...array_values($entry));
        }

        throw new InvalidArgumentException(
            'Each impex.jobs.middleware entry must be a class name, or a list of a class name and its constructor arguments.',
        );
    }
}
