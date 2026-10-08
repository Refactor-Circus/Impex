<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Batch\Exceptions;

use JayI\Impex\Exceptions\ImpexException;

/**
 * A batch item's attempt ended without the item saying how: its worker died,
 * or was killed for running too long. Recorded as the attempt's error, and
 * counted against the item's attempts like any other failure.
 */
final class BatchItemAbandonedException extends ImpexException
{
    public static function leaseLapsed(): self
    {
        return new self('The worker processing this item stopped before it finished, and its lease lapsed.');
    }

    public static function timedOut(): self
    {
        return new self('The worker processing this item was stopped for running past its timeout.');
    }
}
