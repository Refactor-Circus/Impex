<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Support\Concerns;

use RefactorCircus\Impex\Support\JobMiddleware;

/**
 * Gives an Impex job the queue middleware `impex.jobs.middleware` names for
 * it, read when the job runs, not when it was queued.
 */
trait UsesConfiguredMiddleware
{
    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return JobMiddleware::resolve($this);
    }
}
