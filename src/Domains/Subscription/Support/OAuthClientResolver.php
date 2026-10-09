<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Support;

use Illuminate\Http\Request;
use RefactorCircus\Impex\Domains\Subscription\Contracts\ResolvesSubscriber;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriberStatus;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;

/**
 * Finds the subscriber by the OAuth client the request's token was issued
 * to, which covers both a client-credentials token (no user) and a user
 * token issued through the subscriber's client.
 *
 * The token itself is validated by the application's own auth middleware,
 * which must run first; this only reads which client it names.
 */
final class OAuthClientResolver implements ResolvesSubscriber
{
    public function resolve(Request $request): ?SubscriberModel
    {
        $client = $this->clientId($request);

        if ($client === null) {
            return null;
        }

        return SubscriberModel::query()
            ->where('client_id', $client)
            ->where('status', SubscriberStatus::Active)
            ->first();
    }

    private function clientId(Request $request): ?string
    {
        // Set by Passport's client-credentials middleware, or by the
        // application's own.
        $attribute = $request->attributes->get('oauth_client_id');

        if (is_scalar($attribute) && (string) $attribute !== '') {
            return (string) $attribute;
        }

        $user = $request->user();

        if ($user !== null && method_exists($user, 'token')) {
            $token = $user->token();
            $client = is_object($token) ? ($token->client_id ?? null) : null;

            if (is_scalar($client)) {
                return (string) $client;
            }
        }

        return $this->audience($request->bearerToken());
    }

    /**
     * The `aud` claim of a JWT access token. Read without verifying, which is
     * safe only because the auth middleware has already verified it.
     */
    private function audience(?string $token): ?string
    {
        $parts = $token === null ? [] : explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        $audience = is_array($claims) ? ($claims['aud'] ?? null) : null;
        $audience = is_array($audience) ? ($audience[0] ?? null) : $audience;

        return is_scalar($audience) ? (string) $audience : null;
    }
}
