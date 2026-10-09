<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Enums;

enum StepType: string
{
    case Action = 'action';
    case Rollback = 'rollback';
    case SideEffect = 'side_effect';
    case Signal = 'signal';
    case FanOut = 'fan_out';
    case Batch = 'batch';
    case Child = 'child';
    case Timer = 'timer';
}
