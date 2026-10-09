<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Enums;

/**
 * Which subjects a subscription covers: all of them, those its filter
 * matches, or an explicit list of keys.
 */
enum Selection: string
{
    case All = 'all';
    case Filter = 'filter';
    case List = 'list';
}
