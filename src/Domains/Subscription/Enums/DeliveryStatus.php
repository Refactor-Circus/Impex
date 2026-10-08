<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Enums;

enum DeliveryStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
