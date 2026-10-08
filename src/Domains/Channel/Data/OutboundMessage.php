<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Data;

use Illuminate\Contracts\Mail\Mailable;
use JsonException;

/**
 * One thing to send through an outbound channel.
 *
 * The channel supplies where and how; the message supplies what. Anything set
 * here wins over the channel's options, so one channel can serve calls to
 * several paths of the same upstream.
 */
final readonly class OutboundMessage
{
    /**
     * @param  string|null  $body  The encoded body, exactly as it is sent.
     * @param  resource|null  $stream  A body too large to hold in memory, such
     *                                 as a feed file. Recorded by size only.
     * @param  array<string, string>  $headers
     * @param  string|null  $endpoint  A URL, a path on the channel's disk, or
     *                                 the recipients of a mail channel.
     * @param  array{run_id?: string|null, step_id?: string|null, delivery_id?: string|null}  $links
     */
    public function __construct(
        public ?string $body = null,
        public mixed $stream = null,
        public array $headers = [],
        public ?string $endpoint = null,
        public ?string $method = null,
        public ?Mailable $mailable = null,
        public ?string $id = null,
        public ?string $idempotencyKey = null,
        public array $links = [],
    ) {}

    /**
     * A JSON body.
     *
     * @param  array<int|string, mixed>  $data
     * @param  array<string, string>  $headers
     * @param  array{run_id?: string|null, step_id?: string|null, delivery_id?: string|null}  $links
     *
     * @throws JsonException
     */
    public static function json(
        array $data,
        array $headers = [],
        ?string $endpoint = null,
        ?string $method = null,
        ?string $id = null,
        ?string $idempotencyKey = null,
        array $links = [],
    ): self {
        return new self(
            body: json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            headers: ['Content-Type' => 'application/json', ...$headers],
            endpoint: $endpoint,
            method: $method,
            id: $id,
            idempotencyKey: $idempotencyKey,
            links: $links,
        );
    }

    /**
     * A mailable, sent through a mail channel.
     *
     * @param  array{run_id?: string|null, step_id?: string|null, delivery_id?: string|null}  $links
     */
    public static function mail(Mailable $mailable, ?string $to = null, ?string $id = null, array $links = []): self
    {
        return new self(endpoint: $to, mailable: $mailable, id: $id, links: $links);
    }

    public function link(string $key): ?string
    {
        return $this->links[$key] ?? null;
    }
}
