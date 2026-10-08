<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JayI\Impex\Domains\Channel\Contracts\ChannelProfile;
use JayI\Impex\Domains\Channel\Contracts\SignatureValidator;
use JayI\Impex\Domains\Channel\Services\ChannelRegistry;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Services\MessageRecorder;
use JayI\Impex\Domains\Run\Enums\RunTrigger;
use JayI\Impex\Impex;

/**
 * Receives inbound traffic on a named channel.
 *
 * The request is recorded in the ledger before anything else happens — an
 * invalid signature or a request the profile rejects is still evidence of what
 * an upstream sent, and that is exactly what the ledger is for.
 *
 * The response is immediate: the flow is queued, never run inline, so the
 * sender is not held past API Gateway's timeout.
 */
final class ReceiveMessageController
{
    public function __invoke(
        Request $request,
        string $channel,
        ChannelRegistry $channels,
        MessageRecorder $recorder,
        Impex $impex,
    ): JsonResponse {
        $config = $channels->find($channel);

        // An outbound or disabled channel receives nothing, and says no more
        // about itself than a name nobody configured.
        if ($config === null || $config->direction !== Direction::Inbound || ! $config->isActive()) {
            abort(404);
        }

        $validator = app($config->signatureValidator);
        $valid = $validator instanceof SignatureValidator
            ? $validator->isValid($request, $config)
            : false;

        $message = $recorder->record(
            direction: Direction::Inbound,
            channel: $config->name,
            transport: 'http',
            endpoint: $request->fullUrl(),
            body: $request->getContent(),
            method: $request->method(),
            headers: $recorder->filterHeaders($request->headers->all(), $config->storeHeaders),
            signatureValid: $valid,
            idempotencyKey: $this->idempotencyKey($request, $config->idempotencyHeader),
        );

        if (! $valid) {
            return new JsonResponse(['message' => 'Invalid signature.'], 403);
        }

        $profile = app($config->profile);

        if ($profile instanceof ChannelProfile && ! $profile->shouldProcess($request, $config)) {
            return new JsonResponse(['message' => 'Accepted, not processed.', 'message_id' => $message->getKey()], 202);
        }

        if ($config->flow === null) {
            return new JsonResponse(['message' => 'Accepted.', 'message_id' => $message->getKey()], 202);
        }

        $run = $impex->run(
            slug: $config->flow,
            arguments: [$this->decode($request)],
            trigger: RunTrigger::Channel,
            idempotencyKey: $message->idempotency_key,
        );

        $message->update(['run_id' => $run->getKey()]);

        return new JsonResponse([
            'message' => 'Accepted.',
            'message_id' => $message->getKey(),
            'run_id' => $run->getKey(),
        ], 202);
    }

    private function idempotencyKey(Request $request, ?string $header): ?string
    {
        if ($header === null) {
            return null;
        }

        $value = $request->header($header);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        return $payload;
    }
}
