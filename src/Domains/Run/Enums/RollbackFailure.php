<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Enums;

enum RollbackFailure: string
{
    /**
     * Halt the rollback and surface the rollback failure.
     */
    case Halt = 'halt';

    /**
     * Continue rolling back, reporting failures at the end.
     */
    case Continue = 'continue';
}
