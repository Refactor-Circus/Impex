<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Listeners;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Mail\Events\MessageSent;
use JayI\Impex\Domains\Channel\Mail\RecordingMailTransport;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Services\MessageRecorder;
use Symfony\Component\Mime\Address;

/**
 * Records mail sent through a mailer that is not wrapped in the `impex`
 * transport, when `impex.mail.record_unwrapped` asks for it.
 *
 * The fallback for applications that cannot switch their mailer: it sees
 * every mail that was sent, but not one that failed, which is why wrapping
 * the mailer is the better choice.
 */
final class RecordSentMail
{
    public function __construct(
        private readonly MessageRecorder $recorder,
        private readonly Config $config,
    ) {}

    public function handle(MessageSent $event): void
    {
        if ($this->config->get('impex.mail.record_unwrapped') !== true) {
            return;
        }

        $mailer = $event->data['mailer'] ?? null;

        if (is_string($mailer) && $this->config->get("mail.mailers.{$mailer}.transport") === 'impex') {
            return;
        }

        if ($event->message->getHeaders()->has(RecordingMailTransport::HEADER)) {
            return;
        }

        /** @var string $channel */
        $channel = $this->config->get('impex.mail.channel', 'mail');

        $this->recorder->record(
            direction: Direction::Outbound,
            channel: $channel,
            transport: 'mail',
            endpoint: 'mailto:'.implode(',', array_map(
                fn (Address $address): string => $address->getAddress(),
                $event->sent->getEnvelope()->getRecipients(),
            )),
            body: $event->sent->toString(),
        );
    }
}
