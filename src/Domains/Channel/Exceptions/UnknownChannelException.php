<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Exceptions;

use JayI\Impex\Exceptions\ImpexException;

final class UnknownChannelException extends ImpexException
{
    public static function name(string $name): self
    {
        return new self(sprintf(
            'No channel is configured under [%s]. Add it to the `channels` array in config/impex.php, or create it through the channels API.',
            $name,
        ));
    }
}
