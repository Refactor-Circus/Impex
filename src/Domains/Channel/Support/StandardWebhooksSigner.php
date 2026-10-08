<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Support;

use JayI\Impex\Domains\Channel\Contracts\Signer;
use JayI\Impex\Domains\Channel\Data\ChannelConfig;

/**
 * Signs per the Standard Webhooks specification (standardwebhooks.com).
 *
 * The signed content is `{id}.{timestamp}.{body}`, so a captured delivery
 * cannot be replayed under a new id or after the receiver's tolerance window.
 * Every secret the channel holds signs, which is what lets a receiver rotate
 * without refusing anything in between: it verifies against either.
 */
final class StandardWebhooksSigner implements Signer
{
    public function sign(ChannelConfig $channel, string $messageId, int $timestamp, string $body): array
    {
        $signatures = array_map(
            fn (string $secret): string => 'v1,'.self::signature($secret, $messageId, $timestamp, $body),
            $channel->secrets(),
        );

        return [
            'webhook-id' => $messageId,
            'webhook-timestamp' => (string) $timestamp,
            'webhook-signature' => implode(' ', $signatures),
        ];
    }

    /**
     * The base64 HMAC-SHA256 of the signed content. A `whsec_` secret is the
     * spec's base64 encoding of the key; any other is used as it is.
     */
    public static function signature(string $secret, string $messageId, int $timestamp, string $body): string
    {
        $key = str_starts_with($secret, 'whsec_')
            ? (string) base64_decode(substr($secret, 6), true)
            : $secret;

        return base64_encode(hash_hmac('sha256', $messageId.'.'.$timestamp.'.'.$body, $key, true));
    }
}
