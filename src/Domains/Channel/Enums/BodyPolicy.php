<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Enums;

/**
 * Which bodies a channel keeps in the ledger.
 *
 * Every crossing is recorded either way, with its size and a sha256 of the
 * body. The policy only decides whether the body itself is kept: a channel
 * pushing a million product updates a day can keep just the failures, whose
 * bodies are the ones anybody opens.
 */
enum BodyPolicy: string
{
    case All = 'all';
    case Failures = 'failures';
    case None = 'none';

    /**
     * Whether a crossing's body is kept.
     */
    public function keeps(bool $failed): bool
    {
        return match ($this) {
            self::All => true,
            self::Failures => $failed,
            self::None => false,
        };
    }
}
