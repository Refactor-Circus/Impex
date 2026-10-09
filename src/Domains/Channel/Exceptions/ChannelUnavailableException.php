<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Exceptions;

use RefactorCircus\Impex\Exceptions\ImpexException;

final class ChannelUnavailableException extends ImpexException
{
    public static function notOutbound(string $name): self
    {
        return new self(sprintf('The channel [%s] is inbound. Send through an outbound channel.', $name));
    }

    public static function disabled(string $name): self
    {
        return new self(sprintf('The channel [%s] is disabled. Enable it before sending through it.', $name));
    }

    public static function missingOption(string $name, string $option): self
    {
        return new self(sprintf('The channel [%s] has no [%s] option. Set it on the channel.', $name, $option));
    }

    public static function unknownTransport(string $name, string $transport): self
    {
        return new self(sprintf(
            'The channel [%s] uses the transport [%s], which is not registered. Use http, mail or file, or register it with Impex::transports()->extend().',
            $name,
            $transport,
        ));
    }

    public static function notStored(string $name): self
    {
        return new self(sprintf('The channel [%s] is defined in config/impex.php. Change it there.', $name));
    }
}
