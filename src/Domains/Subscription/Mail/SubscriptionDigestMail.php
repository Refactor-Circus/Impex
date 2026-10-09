<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mail;

use Illuminate\Mail\Mailable;

/**
 * A batch of events delivered by mail, for subscribers who want a person to
 * read about the changes rather than a system to receive them.
 */
final class SubscriptionDigestMail extends Mailable
{
    /**
     * @param  list<array<string, mixed>>  $entries
     */
    public function __construct(
        public readonly string $stream,
        public readonly array $entries,
    ) {}

    public function build(): self
    {
        $rows = array_map(function (array $entry): string {
            $topics = is_array($entry['topics'] ?? null) ? implode(', ', array_map('strval', $entry['topics'])) : '';

            return sprintf(
                '<li><strong>%s</strong> %s%s</li>',
                e(is_scalar($entry['subject'] ?? null) ? (string) $entry['subject'] : ''),
                e(is_scalar($entry['type'] ?? null) ? (string) $entry['type'] : ''),
                $topics === '' ? '' : ' ('.e($topics).')',
            );
        }, $this->entries);

        return $this
            ->subject(sprintf('%d change(s) in %s', count($this->entries), $this->stream))
            ->html('<ul>'.implode('', $rows).'</ul>');
    }
}
