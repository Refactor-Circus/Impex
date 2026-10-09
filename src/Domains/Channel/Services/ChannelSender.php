<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Services;

use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;
use RefactorCircus\Impex\Domains\Channel\Data\OutboundMessage;
use RefactorCircus\Impex\Domains\Channel\Data\Receipt;
use RefactorCircus\Impex\Domains\Channel\Exceptions\ChannelUnavailableException;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;

/**
 * Sends a message through a named outbound channel.
 *
 * The one way out: a subscription delivery, a feed upload and a one-off API
 * call all come through here, so each is sent the channel's way and recorded.
 */
final class ChannelSender
{
    public function __construct(
        private readonly ChannelRegistry $channels,
        private readonly TransportManager $transports,
    ) {}

    public function send(string|ChannelConfig $channel, OutboundMessage $message): Receipt
    {
        $config = $channel instanceof ChannelConfig ? $channel : $this->channels->get($channel);

        if ($config->direction !== Direction::Outbound) {
            throw ChannelUnavailableException::notOutbound($config->name);
        }

        if (! $config->isActive()) {
            throw ChannelUnavailableException::disabled($config->name);
        }

        if (! $this->transports->has($config->transport)) {
            throw ChannelUnavailableException::unknownTransport($config->name, $config->transport);
        }

        return $this->transports->driver($config->transport)->send($config, $message);
    }
}
