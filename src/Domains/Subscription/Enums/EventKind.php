<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Enums;

/**
 * What happened to a subject. Removed covers a subject that is gone and
 * one that left a subscription's scope: either way the subscriber should
 * forget it.
 */
enum EventKind: string
{
    case Changed = 'changed';
    case Removed = 'removed';
    case Appended = 'appended';
}
