<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;
use RefactorCircus\Impex\Domains\Channel\Data\OutboundMessage;
use RefactorCircus\Impex\Domains\Channel\Exceptions\ChannelUnavailableException;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Support\StandardWebhooksSigner;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Events\MessageCreatingEvent;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Impex;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

beforeEach(function (): void {
    config()->set('impex.channels', [
        'vendor-hook' => [
            'direction' => 'outbound',
            'transport' => 'http',
            'options' => ['url' => 'https://vendor.test/hooks', 'headers' => ['X-Source' => 'impex']],
            'credentials' => ['signing_secret' => 'whsec_'.base64_encode('topsecret')],
            'store_headers' => ['webhook-id'],
        ],
        'quiet-hook' => [
            'direction' => 'outbound',
            'transport' => 'http',
            'options' => ['url' => 'https://vendor.test/quiet'],
            'body_policy' => 'failures',
        ],
        'drop' => [
            'direction' => 'outbound',
            'transport' => 'file',
            'options' => ['disk' => 'drops', 'path' => 'out/{id}.json'],
        ],
        'ops-mail' => [
            'direction' => 'outbound',
            'transport' => 'mail',
            'options' => ['mailer' => 'array', 'to' => 'ops@example.test'],
        ],
        'supplier-feed' => ['direction' => 'inbound', 'signing_secret' => 'shhh'],
    ]);
});

final class OrderShippedMail extends Mailable
{
    public function build(): self
    {
        return $this->subject('Order shipped')->html('<p>On its way</p>');
    }
}

it('sends through an outbound channel, signs the body and records the crossing', function (): void {
    Http::fake(['vendor.test/*' => Http::response(['ok' => true], 202)]);

    $receipt = app(Impex::class)->send('vendor-hook', new OutboundMessage(
        body: '{"sku":"ABC-1"}',
        headers: ['Content-Type' => 'application/json'],
        id: 'msg_1',
    ));

    expect($receipt->successful)->toBeTrue()
        ->and($receipt->statusCode)->toBe(202);

    Http::assertSent(function (Request $request): bool {
        $expected = StandardWebhooksSigner::signature(
            'whsec_'.base64_encode('topsecret'),
            'msg_1',
            (int) $request->header('webhook-timestamp')[0],
            '{"sku":"ABC-1"}',
        );

        return $request->url() === 'https://vendor.test/hooks'
            && $request->header('X-Source')[0] === 'impex'
            && $request->header('webhook-id')[0] === 'msg_1'
            && $request->header('webhook-signature')[0] === 'v1,'.$expected;
    });

    $message = MessageModel::query()->findOrFail($receipt->messageId);

    expect($message->direction)->toBe(Direction::Outbound)
        ->and($message->channel)->toBe('vendor-hook')
        ->and($message->status_code)->toBe(202)
        ->and($message->headers)->toBe(['webhook-id' => 'msg_1'])
        ->and(app(Impex::class)->body($message))->toBe('{"sku":"ABC-1"}')
        ->and($message->body_sha256)->toBe(hash('sha256', '{"sku":"ABC-1"}'));
});

it('returns a failed receipt for an error answer and keeps the body only on failure', function (): void {
    Http::fake([
        'vendor.test/quiet' => Http::sequence()
            ->push(['ok' => true], 200)
            ->push('upstream down', 503),
    ]);

    $ok = app(Impex::class)->send('quiet-hook', ['n' => 1]);
    $failed = app(Impex::class)->send('quiet-hook', ['n' => 2]);

    expect($ok->successful)->toBeTrue()
        ->and($failed->failed())->toBeTrue()
        ->and($failed->statusCode)->toBe(503)
        ->and($failed->error['response'] ?? null)->toBe('upstream down');

    $kept = MessageModel::query()->findOrFail($failed->messageId);
    $dropped = MessageModel::query()->findOrFail($ok->messageId);

    // Both are recorded, with size and hash; only the failure keeps its body.
    expect(app(Impex::class)->body($kept))->toBe('{"n":2}')
        ->and(app(Impex::class)->body($dropped))->toBeNull()
        ->and($dropped->bytes)->toBe(7)
        ->and($dropped->body_sha256)->toBe(hash('sha256', '{"n":1}'));
});

it('records a send that never got an answer', function (): void {
    Http::fake(['vendor.test/*' => Http::failedConnection('Connection timed out')]);

    $receipt = app(Impex::class)->send('vendor-hook', ['n' => 1]);

    $message = MessageModel::query()->findOrFail($receipt->messageId);

    expect($receipt->failed())->toBeTrue()
        ->and($message->status_code)->toBeNull()
        ->and($message->error['message'] ?? '')->toContain('Connection timed out');
});

it('refuses to send through an inbound or a disabled channel', function (): void {
    expect(fn () => app(Impex::class)->send('supplier-feed', ['n' => 1]))
        ->toThrow(ChannelUnavailableException::class, 'inbound');

    ChannelModel::factory()->create(['name' => 'paused', 'status' => 'disabled']);

    expect(fn () => app(Impex::class)->send('paused', ['n' => 1]))
        ->toThrow(ChannelUnavailableException::class, 'disabled');
});

it('guards an endpoint an outside party supplied against private addresses', function (): void {
    Http::fake();

    $channel = ChannelModel::factory()->create([
        'name' => 'subscriber-hook',
        'options' => ['url' => 'https://10.0.0.5/hook'],
        'owner_type' => 'subscriber',
        'owner_id' => '1',
    ]);

    $receipt = app(Impex::class)->send($channel->name, ['n' => 1]);

    expect($receipt->failed())->toBeTrue()
        ->and($receipt->error['message'] ?? '')->toContain('private or reserved');

    Http::assertNothingSent();
});

it('writes to a disk through a file channel', function (): void {
    Storage::fake('drops');

    $receipt = app(Impex::class)->send('drop', new OutboundMessage(body: '{"a":1}', id: 'feed-1'));

    Storage::disk('drops')->assertExists('out/feed-1.json');

    expect(MessageModel::query()->findOrFail($receipt->messageId)->endpoint)->toBe('drops://out/feed-1.json');
});

it('sends a mailable through a mail channel and records it once', function (): void {
    config()->set('mail.mailers.impex', ['transport' => 'impex', 'mailer' => 'array']);
    config()->set('impex.channels.ops-mail.options.mailer', 'impex');

    $receipt = app(Impex::class)->send('ops-mail', OutboundMessage::mail(new OrderShippedMail));

    $message = MessageModel::query()->findOrFail($receipt->messageId);

    expect($receipt->successful)->toBeTrue()
        ->and($message->transport)->toBe('mail')
        ->and($message->endpoint)->toBe('mailto:ops@example.test')
        ->and(app(Impex::class)->body($message))->toContain('Order shipped')
        // Sent through the recording mailer too, yet recorded only once.
        ->and(MessageModel::query()->count())->toBe(1);
});

it('records every mail sent through the impex mailer, failures included', function (): void {
    config()->set('mail.mailers.impex', ['transport' => 'impex', 'mailer' => 'array', 'channel' => 'app-mail']);

    Mail::mailer('impex')->raw('Welcome aboard', function ($mail): void {
        $mail->to('new@example.test')->subject('Welcome');
    });

    $sent = MessageModel::query()->where('channel', 'app-mail')->firstOrFail();

    expect($sent->endpoint)->toBe('mailto:new@example.test')
        ->and($sent->error)->toBeNull();

    Mail::extend('broken', fn (): AbstractTransport => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('SMTP said no');
        }

        public function __toString(): string
        {
            return 'broken';
        }
    });
    config()->set('mail.mailers.broken', ['transport' => 'broken']);
    config()->set('mail.mailers.impex-broken', ['transport' => 'impex', 'mailer' => 'broken', 'channel' => 'app-mail']);

    expect(fn () => Mail::mailer('impex-broken')->raw('Hello', fn ($mail) => $mail->to('x@example.test')))
        ->toThrow(TransportException::class, 'SMTP said no');

    $failed = MessageModel::query()->where('channel', 'app-mail')->latest('occurred_at')->get()
        ->first(fn (MessageModel $m): bool => $m->error !== null);

    expect($failed?->error['message'] ?? null)->toBe('SMTP said no');
});

it('writes ledger rows without firing a model event for each', function (): void {
    Http::fake(['vendor.test/*' => Http::response()]);
    Event::fake([MessageCreatingEvent::class]);

    app(Impex::class)->send('vendor-hook', ['n' => 1]);

    Event::assertNotDispatched(MessageCreatingEvent::class);
    expect(MessageModel::query()->count())->toBe(1);
});

it('describes a channel without its secrets', function (): void {
    $described = ChannelConfig::fromArray('x', [
        'direction' => 'outbound',
        'credentials' => ['signing_secret' => 'never-shown'],
    ])->describe();

    expect(json_encode($described))->not->toContain('never-shown')
        ->and($described['verifies_signatures'])->toBeTrue();
});
