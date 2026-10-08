<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Contracts;

use JayI\Impex\Domains\Channel\Data\ChannelConfig;

/**
 * Signs an outbound body so the receiver can tell it came from us, unaltered.
 */
interface Signer
{
    /**
     * The headers that carry the signature.
     *
     * @return array<string, string>
     */
    public function sign(ChannelConfig $channel, string $messageId, int $timestamp, string $body): array;
}
