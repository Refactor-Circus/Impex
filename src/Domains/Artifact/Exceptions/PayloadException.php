<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Artifact\Exceptions;

use RefactorCircus\Impex\Exceptions\ImpexException;
use Throwable;

final class PayloadException extends ImpexException
{
    public static function artifactMissing(string $artifactId): self
    {
        return new self(sprintf(
            'The artifact [%s] backing this payload is missing. It may have been pruned before the row referencing it.',
            $artifactId,
        ));
    }

    public static function notSerializable(Throwable $previous): self
    {
        return new self(
            'Impex payloads must be JSON serializable. Return plain arrays and scalars from flow actions.',
            previous: $previous,
        );
    }
}
