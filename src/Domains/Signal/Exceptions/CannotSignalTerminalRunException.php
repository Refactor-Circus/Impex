<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Signal\Exceptions;

use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Exceptions\ImpexException;

/**
 * A signal was delivered to a run that has already finished.
 *
 * Accepting it silently would be worse than failing: the row would sit
 * unconsumed forever while the caller believed the run had been told something.
 */
final class CannotSignalTerminalRunException extends ImpexException
{
    public static function status(string $runId, RunStatus $status): self
    {
        return new self(sprintf(
            'The run [%s] is %s, so the signal cannot be delivered — nothing will ever consume it. '.
            'Use signalIfRunning() if a finished run should be a no-op rather than an error.',
            $runId,
            $status->value,
        ));
    }
}
