<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Tests\Fixtures\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Message\Policies\MessagePolicy;

/**
 * Nobody may list the ledger.
 */
final class RefusingMessagePolicy extends MessagePolicy
{
    public function viewAny(Model $user): bool
    {
        return false;
    }
}
