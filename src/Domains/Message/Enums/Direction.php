<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Enums;

enum Direction: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
