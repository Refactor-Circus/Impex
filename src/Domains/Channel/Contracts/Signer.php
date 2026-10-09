<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Contracts;

use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;

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
