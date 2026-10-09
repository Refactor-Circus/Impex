<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Exceptions;

use RefactorCircus\Impex\Domains\Flow\Support\Flow;
use RefactorCircus\Impex\Exceptions\ImpexException;

final class UnknownFlowException extends ImpexException
{
    public static function slug(string $slug): self
    {
        return new self(sprintf('No flow is registered under [%s].', $slug));
    }

    public static function notAFlow(string $class): self
    {
        return new self(sprintf(
            'The flow [%s] must extend %s and declare a public handle() method.',
            $class,
            Flow::class,
        ));
    }
}
