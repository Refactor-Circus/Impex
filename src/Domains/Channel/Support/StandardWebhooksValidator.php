<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Support;

use Illuminate\Http\Request;
use RefactorCircus\Impex\Domains\Channel\Contracts\SignatureValidator;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;

/**
 * Verifies an inbound delivery signed per the Standard Webhooks specification.
 *
 * A timestamp outside the tolerance is refused even with a good signature: it
 * is a replay of a captured delivery, or a clock badly enough wrong that the
 * sender should hear about it.
 */
final class StandardWebhooksValidator implements SignatureValidator
{
    public function isValid(Request $request, ChannelConfig $config): bool
    {
        // Fails closed, as the HMAC validator does: a channel with no secret
        // cannot authenticate anything.
        if (! $config->verifiesSignatures()) {
            return false;
        }

        $id = $request->header('webhook-id');
        $timestamp = $request->header('webhook-timestamp');
        $signatures = $request->header('webhook-signature');

        if (! is_string($id) || ! is_string($timestamp) || ! is_string($signatures) || ! ctype_digit($timestamp)) {
            return false;
        }

        /** @var int $tolerance */
        $tolerance = $config->option('tolerance_seconds', 300);

        if (abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $provided = array_map(
            fn (string $part): string => str_starts_with($part, 'v1,') ? substr($part, 3) : '',
            explode(' ', $signatures),
        );

        foreach ($config->secrets() as $secret) {
            $expected = StandardWebhooksSigner::signature($secret, $id, (int) $timestamp, $request->getContent());

            foreach ($provided as $signature) {
                if ($signature !== '' && hash_equals($expected, $signature)) {
                    return true;
                }
            }
        }

        return false;
    }
}
