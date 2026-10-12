<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Exceptions;

use RefactorCircus\Impex\Exceptions\ImpexException;
use RefactorCircus\Impex\Support\Tables;

final class DisabledFlowException extends ImpexException
{
    public static function slug(string $slug): self
    {
        return new self(sprintf(
            'The flow [%s] is disabled. Re-enable it by removing or updating its row in %s.',
            $slug,
            Tables::name('flows'),
        ));
    }
}
