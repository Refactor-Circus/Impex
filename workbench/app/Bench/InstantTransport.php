<?php

declare(strict_types=1);

namespace Workbench\App\Bench;

use RefactorCircus\Impex\Domains\Channel\Contracts\Transport;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;
use RefactorCircus\Impex\Domains\Channel\Data\OutboundMessage;
use RefactorCircus\Impex\Domains\Channel\Data\Receipt;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Services\MessageRecorder;

/**
 * An endpoint that accepts everything at once, still recorded in the ledger,
 * so a benchmark measures Impex rather than the network or an HTTP fake that
 * keeps every request in memory.
 */
final class InstantTransport implements Transport
{
    public static int $sent = 0;

    public function __construct(private readonly MessageRecorder $recorder) {}

    public function send(ChannelConfig $channel, OutboundMessage $message): Receipt
    {
        self::$sent++;

        $recorded = $this->recorder->record(
            direction: Direction::Outbound,
            channel: $channel->name,
            transport: 'bench',
            endpoint: (string) $channel->option('url'),
            body: $message->body,
            method: 'POST',
            statusCode: 200,
            durationMs: 0,
            deliveryId: $message->link('delivery_id'),
        );

        return new Receipt(successful: true, messageId: (string) $recorded->getKey(), statusCode: 200, durationMs: 0);
    }
}
