<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Transports;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use JayI\Impex\Domains\Channel\Contracts\Signer;
use JayI\Impex\Domains\Channel\Contracts\Transport;
use JayI\Impex\Domains\Channel\Data\ChannelConfig;
use JayI\Impex\Domains\Channel\Data\OutboundMessage;
use JayI\Impex\Domains\Channel\Data\Receipt;
use JayI\Impex\Domains\Channel\Exceptions\ChannelUnavailableException;
use JayI\Impex\Domains\Channel\Support\EndpointGuard;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Services\MessageRecorder;
use Throwable;

/**
 * Sends over HTTP: webhooks, API calls, feed uploads.
 *
 * Options: `url`, `method` (POST), `headers`, `timeout`, `connect_timeout`,
 * `gzip`, `signer` (a Signer class; used whenever the channel has a secret),
 * `guard` (forces the endpoint guard on a channel the application owns).
 *
 * A response outside 2xx and a request that never got one — a timeout, a
 * refused connection — are both recorded and returned as a failed receipt
 * rather than thrown, so a caller retrying deliveries decides what a failure
 * means, not the transport.
 */
final class HttpTransport implements Transport
{
    public function __construct(
        private readonly Http $http,
        private readonly MessageRecorder $recorder,
        private readonly EndpointGuard $guard,
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    public function send(ChannelConfig $channel, OutboundMessage $message): Receipt
    {
        $url = $message->endpoint ?? $this->string($channel->option('url'))
            ?? throw ChannelUnavailableException::missingOption($channel->name, 'url');
        $method = strtoupper($message->method ?? $this->string($channel->option('method')) ?? 'POST');
        $id = $message->id ?? 'msg_'.Str::ulid();
        $body = $message->body ?? '';

        $headers = [...$this->headers($channel), ...$message->headers];
        $headers = [...$headers, ...$this->signature($channel, $id, $body)];

        $wire = $body;

        if ($message->stream === null && $body !== '' && $channel->option('gzip') === true) {
            $wire = (string) gzencode($body);
            $headers['Content-Encoding'] = 'gzip';
        }

        $startedAt = microtime(true);
        $status = null;
        $error = null;
        $response = null;

        try {
            $request = $this->http
                ->withHeaders($headers)
                ->timeout($this->seconds($channel, 'timeout'))
                ->connectTimeout($this->seconds($channel, 'connect_timeout'));

            if ($this->guarded($channel)) {
                $request = $request->withOptions(['curl' => [CURLOPT_RESOLVE => [
                    $this->guard->resolve($url, $this->config->get('impex.outbound.allow_insecure') === true),
                ]]]);
            }

            $contentType = $headers['Content-Type'] ?? 'application/json';
            $request = $request->withBody($message->stream === null ? $wire : Utils::streamFor($message->stream), $contentType);

            $answer = $request->send($method, $url);
            $status = $answer->status();
            $response = $this->preview($answer);

            if (! $answer->successful()) {
                $error = ['message' => sprintf('The endpoint answered %d.', $status), 'response' => $response];
            }
        } catch (Throwable $e) {
            // An unsafe endpoint lands here too: recorded, and a failed
            // receipt, like any other send that could not go out.
            $error = ['message' => $e->getMessage(), 'class' => $e::class];
        }

        $recorded = $this->recorder->record(
            direction: Direction::Outbound,
            channel: $channel->name,
            transport: 'http',
            endpoint: $url,
            body: $message->stream === null ? $body : null,
            method: $method,
            statusCode: $status,
            headers: $this->recorder->filterHeaders($headers, $channel->storeHeaders),
            runId: $message->link('run_id'),
            stepId: $message->link('step_id'),
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            error: $error,
            deliveryId: $message->link('delivery_id'),
            bytes: $this->streamSize($message),
        );

        return new Receipt(
            successful: $error === null,
            messageId: (string) $recorded->getKey(),
            statusCode: $status,
            durationMs: $recorded->duration_ms,
            error: $error,
            response: $response,
        );
    }

    /**
     * @return array<string, string>
     */
    private function headers(ChannelConfig $channel): array
    {
        $headers = $channel->option('headers', []);

        /** @var array<string, string> $headers */
        $headers = is_array($headers) ? $headers : [];

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function signature(ChannelConfig $channel, string $id, string $body): array
    {
        if ($channel->secrets() === []) {
            return [];
        }

        $class = $this->string($channel->option('signer')) ?? $this->string($this->config->get('impex.outbound.signer'));

        if ($class === null) {
            return [];
        }

        $signer = $this->container->make($class);

        return $signer instanceof Signer ? $signer->sign($channel, $id, time(), $body) : [];
    }

    /**
     * A subscriber's own endpoint is always guarded; one the application
     * defines is trusted unless its options ask for the guard, or the guard
     * is switched off altogether, as a test suite does.
     */
    private function guarded(ChannelConfig $channel): bool
    {
        if ($this->config->get('impex.outbound.guard', true) === false) {
            return false;
        }

        return $channel->isOwned() || $channel->option('guard') === true;
    }

    private function seconds(ChannelConfig $channel, string $option): int
    {
        $value = $channel->option($option) ?? $this->config->get('impex.outbound.'.$option);

        return is_numeric($value) ? (int) $value : 10;
    }

    private function preview(Response $response): ?string
    {
        $body = $response->body();

        if ($body === '') {
            return null;
        }

        /** @var int $length */
        $length = $this->config->get('impex.messages.preview_bytes', 2048);

        return mb_strcut($body, 0, $length);
    }

    private function streamSize(OutboundMessage $message): ?int
    {
        if (! is_resource($message->stream)) {
            return null;
        }

        $stat = fstat($message->stream);

        return $stat === false ? null : $stat['size'];
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
