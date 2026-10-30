<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Tests\WithoutAtrium;

use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RefactorCircus\Impex\ImpexServiceProvider;

/**
 * Impex as an application without Atrium boots it: an explicit provider list
 * with no Atrium provider and no package discovery, so Atrium is never
 * registered from Composer's metadata, and no workbench code that names
 * Atrium (the test host, the workbench provider) is loaded. The bootstrap has
 * already taken Atrium out of the autoloader.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Read by Testbench: discover no package, Atrium included. Testbench's
     * default already is false; it is stated here because this suite's proof
     * depends on it.
     *
     * @var bool
     */
    protected $enablesPackageDiscoveries = false;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            ImpexServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        $app['config']->set('impex.authorization', false);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('impex.cache.store', 'array');
        $app['config']->set('filesystems.default', 'local');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');
    }
}
