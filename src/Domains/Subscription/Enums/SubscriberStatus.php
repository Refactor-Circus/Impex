<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Enums;

enum SubscriberStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
