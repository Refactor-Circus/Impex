<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Enums;

enum DeliveryStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
