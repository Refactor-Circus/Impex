<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Transports;

use Illuminate\Contracts\Mail\Factory as Mail;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use JayI\Impex\Domains\Channel\Contracts\Transport;
use JayI\Impex\Domains\Channel\Data\ChannelConfig;
use JayI\Impex\Domains\Channel\Data\OutboundMessage;
use JayI\Impex\Domains\Channel\Data\Receipt;
use JayI\Impex\Domains\Channel\Exceptions\ChannelUnavailableException;
use JayI\Impex\Domains\Channel\Mail\RecordingMailTransport;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Services\MessageRecorder;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Sends mail through one of the application's mailers.
 *
 * Options: `mailer` (the default mailer when unset) and `to`, the recipients
 * when a message names none. A message carries a Mailable, or a plain body
 * sent as text.
 */
final class MailTransport implements Transport
{
    public function __construct(
        private readonly Mail $mail,
        private readonly MessageRecorder $recorder,
    ) {}

    public function send(ChannelConfig $channel, OutboundMessage $message): Receipt
    {
        $to = $this->recipients($channel, $message);
        $mailer = $this->mail->mailer(is_string($channel->option('mailer')) ? $channel->option('mailer') : null);
        $startedAt = microtime(true);
        $sent = null;
        $error = null;

        try {
            if ($message->mailable instanceof Mailable) {
                // Marked so a mailer wrapped in the recording transport does
                // not record the same mail a second time.
                $message->mailable->withSymfonyMessage(function (Email $email) use ($channel): void {
                    $email->getHeaders()->addTextHeader(RecordingMailTransport::HEADER, $channel->name);
                });
            }

            $sent = $message->mailable !== null
                ? $mailer->to($to)->send($message->mailable)
                : $this->raw($mailer, $channel, $message, $to);
        } catch (Throwable $e) {
            $error = ['message' => $e->getMessage(), 'class' => $e::class];
        }

        $recorded = $this->recorder->record(
            direction: Direction::Outbound,
            channel: $channel->name,
            transport: 'mail',
            endpoint: 'mailto:'.implode(',', $to),
            body: $sent instanceof SentMessage ? $sent->toString() : $message->body,
            runId: $message->link('run_id'),
            stepId: $message->link('step_id'),
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            error: $error,
            deliveryId: $message->link('delivery_id'),
        );

        return new Receipt(
            successful: $error === null,
            messageId: (string) $recorded->getKey(),
            durationMs: $recorded->duration_ms,
            error: $error,
        );
    }

    /**
     * @param  array<int, string>  $to
     */
    private function raw(Mailer $mailer, ChannelConfig $channel, OutboundMessage $message, array $to): ?SentMessage
    {
        $subject = is_string($channel->option('subject')) ? $channel->option('subject') : $channel->name;

        $sent = $mailer->raw((string) $message->body, function (Message $mail) use ($to, $subject, $channel): void {
            $mail->to($to)->subject($subject);
            $mail->getSymfonyMessage()->getHeaders()->addTextHeader(RecordingMailTransport::HEADER, $channel->name);
        });

        return $sent instanceof SentMessage ? $sent : null;
    }

    /**
     * @return array<int, string>
     */
    private function recipients(ChannelConfig $channel, OutboundMessage $message): array
    {
        $to = $message->endpoint ?? $channel->option('to');

        $recipients = is_array($to) ? $to : array_map('trim', explode(',', (string) $to));
        $recipients = array_values(array_filter($recipients, fn (mixed $address): bool => is_string($address) && $address !== ''));

        if ($recipients === [] && $message->mailable === null) {
            throw ChannelUnavailableException::missingOption($channel->name, 'to');
        }

        /** @var array<int, string> $recipients */
        return $recipients;
    }
}
