<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Enums;

enum ChannelStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
