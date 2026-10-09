<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Exceptions;

use RefactorCircus\Foundation\Exceptions\PackageException;

/**
 * A rule of Impex that the caller broke.
 *
 * The message is written for whoever made the call, so the JSON API answers
 * with it as a 409 and the MCP tools return it as the error text.
 */
abstract class ImpexException extends PackageException
{
    //
}
