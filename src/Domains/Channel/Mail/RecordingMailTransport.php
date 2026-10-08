<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Mail;

use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Services\MessageRecorder;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;
use Throwable;

/**
 * Wraps a real mail transport so every mail the application sends lands in
 * the ledger: Mailables, notifications, password resets, all of it.
 *
 * Configure a mailer with the `impex` transport naming the mailer that really
 * sends, and make it the default:
 *
 *     'impex' => ['transport' => 'impex', 'mailer' => 'ses', 'channel' => 'mail'],
 *
 * A send that fails is recorded with its error and the exception rethrown, so
 * the application sees the failure exactly as it would without Impex.
 */
final class RecordingMailTransport implements TransportInterface
{
    /**
     * Set by Impex's own mail channel, which records its sends itself.
     */
    public const string HEADER = 'X-Impex-Channel';

    public function __construct(
        private readonly TransportInterface $inner,
        private readonly MessageRecorder $recorder,
        private readonly string $channel,
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message && $message->getHeaders()->has(self::HEADER)) {
            return $this->inner->send($message, $envelope);
        }

        $startedAt = microtime(true);

        try {
            $sent = $this->inner->send($message, $envelope);
        } catch (Throwable $e) {
            $this->record($message, $envelope, $startedAt, ['message' => $e->getMessage(), 'class' => $e::class]);

            throw $e;
        }

        $this->record($message, $sent?->getEnvelope() ?? $envelope, $startedAt, null, $sent?->toString());

        return $sent;
    }

    public function __toString(): string
    {
        return 'impex+'.$this->inner;
    }

    /**
     * @param  array<string, mixed>|null  $error
     */
    private function record(RawMessage $message, ?Envelope $envelope, float $startedAt, ?array $error, ?string $raw = null): void
    {
        $this->recorder->record(
            direction: Direction::Outbound,
            channel: $this->channel,
            transport: 'mail',
            endpoint: 'mailto:'.implode(',', $this->recipients($message, $envelope)),
            body: $raw ?? $message->toString(),
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            error: $error,
        );
    }

    /**
     * @return array<int, string>
     */
    private function recipients(RawMessage $message, ?Envelope $envelope): array
    {
        try {
            $envelope ??= Envelope::create($message);
        } catch (Throwable) {
            return [];
        }

        return array_map(fn (Address $address): string => $address->getAddress(), $envelope->getRecipients());
    }
}
