<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Support;

use RefactorCircus\Impex\Domains\Channel\Contracts\Signer;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;

/**
 * The common, simpler shape: a hex HMAC-SHA256 of the raw body under the
 * channel's current secret, in the channel's signature header. The mirror of
 * HmacSha256Validator.
 */
final class HmacSha256Signer implements Signer
{
    public function sign(ChannelConfig $channel, string $messageId, int $timestamp, string $body): array
    {
        $secret = $channel->secrets()[0] ?? null;

        if ($secret === null) {
            return [];
        }

        return [$channel->signatureHeader => hash_hmac('sha256', $body, $secret)];
    }
}
