<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Enums;

enum StepPhase: string
{
    /**
     * Steps recorded while replaying the flow forward.
     */
    case Forward = 'forward';

    /**
     * Steps recorded while rolling the flow back.
     */
    case Rollback = 'rollback';
}
