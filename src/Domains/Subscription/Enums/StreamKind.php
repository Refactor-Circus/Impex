<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Enums;

/**
 * How a stream reports change.
 *
 * A snapshot stream is touched: Impex looks at the subject itself and works
 * out what changed, topic by topic, so ten saves that end where they started
 * send nothing. An append stream publishes discrete events, an order placed
 * or shipped, each of which is sent as it is.
 */
enum StreamKind: string
{
    case Snapshot = 'snapshot';
    case Append = 'append';
}
