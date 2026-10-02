<?php

declare(strict_types=1);

namespace JayI\Impex\Tests;

use JayI\PennantPlus\PennantPlusServiceProvider;
use Laravel\Pennant\PennantServiceProvider;

/**
 * Impex booted with jayi/pennantplus answering Atrium's feature checks.
 */
abstract class PennantPlusTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PennantServiceProvider::class,
            PennantPlusServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('pennant.default', 'array');
    }
}
