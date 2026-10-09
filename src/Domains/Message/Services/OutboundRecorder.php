<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Services;

use Closure;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use Throwable;

/**
 * Records outbound HTTP as the mirror of an inbound channel.
 *
 * Returns a PendingRequest with a recorder middleware attached, so every call
 * an action makes lands in the ledger with its run and step attached —
 * including the calls that never got an answer. A timeout or a refused
 * connection is the crossing someone opens the ledger to find.
 */
final class OutboundRecorder
{
    public function __construct(
        private readonly Http $http,
        private readonly MessageRecorder $recorder,
        private readonly ChannelRegistry $channels,
    ) {}

    /**
     * A client whose traffic is recorded against the given channel.
     */
    public function client(string $channel, ?string $runId = null, ?string $stepId = null): PendingRequest
    {
        return $this->http->withMiddleware($this->middleware($channel, $runId, $stepId));
    }

    private function middleware(string $channel, ?string $runId, ?string $stepId): Closure
    {
        return fn (callable $handler): Closure => function (RequestInterface $request, array $options) use (
            $handler,
            $channel,
            $runId,
            $stepId,
        ): PromiseInterface {
            $startedAt = microtime(true);
            [$body, $bytes] = $this->body($request);

            $record = fn (?int $status, ?array $error) => $this->recorder->record(
                direction: Direction::Outbound,
                channel: $channel,
                transport: 'http',
                endpoint: (string) $request->getUri(),
                body: $body,
                method: $request->getMethod(),
                statusCode: $status,
                headers: $this->headers($channel, $request),
                runId: $runId,
                stepId: $stepId,
                durationMs: (int) round((microtime(true) - $startedAt) * 1000),
                error: $error,
                bytes: $bytes,
            );

            $failure = function (mixed $reason) use ($record): void {
                $record(null, [
                    'message' => $reason instanceof Throwable ? $reason->getMessage() : 'The request was rejected.',
                    'class' => $reason instanceof Throwable ? $reason::class : null,
                ]);
            };

            // A handler may throw rather than reject — the HTTP fake does for
            // a failed connection — and either way the attempt is recorded.
            try {
                $promise = $handler($request, $options);
            } catch (Throwable $e) {
                $failure($e);

                throw $e;
            }

            return $promise->then(
                function (ResponseInterface $response) use ($record): ResponseInterface {
                    $record($response->getStatusCode(), null);

                    return $response;
                },
                function (mixed $reason) use ($failure): PromiseInterface {
                    $failure($reason);

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    /**
     * The body to record, without consuming a stream the request still has
     * to send. A body that cannot be rewound, or is larger than an artifact
     * should hold in memory, is recorded by size alone.
     *
     * @return array{0: string|null, 1: int|null}
     */
    private function body(RequestInterface $request): array
    {
        $stream = $request->getBody();
        $size = $stream->getSize();

        /** @var int $limit */
        $limit = config('impex.messages.max_recorded_bytes', 16 * 1024 * 1024);

        if (! $stream->isSeekable() || $size === null || $size > $limit) {
            return [null, $size];
        }

        $body = (string) $stream;
        $stream->rewind();

        return [$body, null];
    }

    /**
     * The request headers the channel asked to keep, if it is a known one.
     *
     * @return array<string, mixed>|null
     */
    private function headers(string $channel, RequestInterface $request): ?array
    {
        $keep = $this->channels->find($channel)->storeHeaders ?? [];

        return $this->recorder->filterHeaders($request->getHeaders(), $keep);
    }
}
