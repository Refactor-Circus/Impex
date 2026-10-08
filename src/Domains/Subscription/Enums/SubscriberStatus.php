<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Enums;

enum SubscriberStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
