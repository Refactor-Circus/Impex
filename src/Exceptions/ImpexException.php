<?php

declare(strict_types=1);

namespace JayI\Impex\Exceptions;

use JayI\Foundation\Exceptions\PackageException;

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
