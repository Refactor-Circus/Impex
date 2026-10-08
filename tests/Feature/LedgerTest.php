<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Message\Services\MessageRecorder;
use JayI\Impex\Impex;

function recordInbound(string $body, ?string $key = null): MessageModel
{
    return app(MessageRecorder::class)->record(
        direction: Direction::Inbound,
        channel: 'supplier-feed',
        transport: 'http',
        endpoint: 'https://app.test/impex/channels/supplier-feed',
        body: $body,
        idempotencyKey: $key,
    );
}

it('keeps a body larger than the preview whole', function (): void {
    $body = str_repeat('x', 10 * 1024);

    $message = recordInbound($body);

    expect(app(Impex::class)->body(MessageModel::query()->findOrFail($message->getKey())))->toBe($body)
        ->and(strlen((string) $message->body_preview))->toBe(2048);
});

it('keeps a body above the artifact threshold on the artifact disk', function (): void {
    config()->set('impex.artifacts.inline_threshold', 1024);

    $body = str_repeat('y', 4096);

    $message = MessageModel::query()->findOrFail(recordInbound($body)->getKey());

    expect($message->body_artifact_id)->not->toBeNull()
        ->and(app(Impex::class)->body($message))->toBe($body);
});

it('keeps a binary body, which a text column cannot hold, as base64', function (): void {
    $body = random_bytes(64)."\xff\xfe";

    $message = MessageModel::query()->findOrFail(recordInbound($body)->getKey());

    expect($message->body_encoding)->toBe('base64')
        ->and($message->body_preview)->toBeNull()
        ->and(app(Impex::class)->body($message))->toBe($body);
});

it('returns the first row for a redelivered idempotency key instead of failing', function (): void {
    $first = recordInbound('{"a":1}', 'delivery-1');
    $again = recordInbound('{"a":1}', 'delivery-1');

    expect($again->getKey())->toBe($first->getKey())
        ->and(MessageModel::query()->count())->toBe(1);
});

it('records an outbound HTTP call that never got an answer', function (): void {
    Http::fake(['carrier.test/*' => Http::failedConnection('Could not resolve host')]);

    try {
        app(Impex::class)->http('carrier-api')->post('https://carrier.test/rates', ['zip' => '90210']);
    } catch (Throwable) {
        // The caller sees the failure as it would without Impex.
    }

    $message = MessageModel::query()->firstOrFail();

    expect($message->channel)->toBe('carrier-api')
        ->and($message->status_code)->toBeNull()
        ->and($message->error['message'] ?? '')->toContain('Could not resolve host')
        ->and(app(Impex::class)->body($message))->toBe('{"zip":"90210"}');
});
