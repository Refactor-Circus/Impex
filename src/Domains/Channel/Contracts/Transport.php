<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Contracts;

use JayI\Impex\Domains\Channel\Data\ChannelConfig;
use JayI\Impex\Domains\Channel\Data\OutboundMessage;
use JayI\Impex\Domains\Channel\Data\Receipt;

/**
 * How bytes leave the application: HTTP, mail, a file on a disk.
 *
 * A transport sends and records. Every send lands in the ledger, successful
 * or not, so the ledger stays the one place to answer "did we send it?".
 * Register your own with Impex::transports()->extend($name, $factory).
 */
interface Transport
{
    public function send(ChannelConfig $channel, OutboundMessage $message): Receipt;
}
