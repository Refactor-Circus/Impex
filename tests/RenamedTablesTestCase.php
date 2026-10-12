<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Tests;

use RefactorCircus\Impex\Support\Tables;

/**
 * Every Impex table renamed through `impex.tables` before migrating.
 */
abstract class RenamedTablesTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach (array_keys(Tables::DEFAULTS) as $key) {
            $app['config']->set('impex.tables.'.$key, 'crm_'.$key);
        }
    }
}
