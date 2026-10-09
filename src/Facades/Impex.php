<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RefactorCircus\Impex\Impex
 */
class Impex extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \RefactorCircus\Impex\Impex::class;
    }
}
