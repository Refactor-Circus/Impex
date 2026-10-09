<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Exceptions;

use RefactorCircus\Impex\Exceptions\ImpexException;

final class SubscriptionException extends ImpexException
{
    public static function unknownStream(string $stream): self
    {
        return new self(sprintf(
            'No stream is registered under [%s]. Register it in `streams` in config/impex.php or with Impex::streams()->register().',
            $stream,
        ));
    }

    /**
     * @param  list<string>  $known
     */
    public static function unknownTopics(string $stream, array $known): self
    {
        return new self(sprintf('The stream [%s] has no such topic. Choose from: %s.', $stream, implode(', ', $known)));
    }

    /**
     * @param  list<string>  $known
     */
    public static function unknownFormat(string $stream, string $format, array $known): self
    {
        return new self(sprintf('The stream [%s] has no [%s] format. Choose from: %s.', $stream, $format, implode(', ', $known)));
    }

    public static function exportUnsupported(string $stream): self
    {
        return new self(sprintf('The stream [%s] cannot export. Follow the feed from the start instead.', $stream));
    }

    public static function notPushed(string $subscription): self
    {
        return new self(sprintf('The subscription [%s] is feed-only. Give it an endpoint to have events pushed.', $subscription));
    }

    public static function tooManyTopics(string $stream): self
    {
        return new self(sprintf('The stream [%s] declares more than 31 topics. Merge some.', $stream));
    }

    public static function notSnapshot(string $stream): self
    {
        return new self(sprintf('The stream [%s] is an append stream. Publish events to it instead of touching subjects.', $stream));
    }

    public static function notAppend(string $stream): self
    {
        return new self(sprintf('The stream [%s] is a snapshot stream. Touch its subjects instead of publishing events.', $stream));
    }
}
