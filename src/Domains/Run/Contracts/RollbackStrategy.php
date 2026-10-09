<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Contracts;

use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunStepModel;

/**
 * Decides what a failed run unwinds, and in what order.
 *
 * The shipped strategy walks completed steps in reverse, rolling back one at a
 * time unless their unit asked to go together. Bind your own to change the
 * order, batch differently, or refuse to unwind past a point.
 */
interface RollbackStrategy
{
    /**
     * Queue the next rollback for a run that is unwinding.
     *
     * Called on every drive while the run is rolling back. Return false when
     * there is nothing left to undo — the engine then finishes the run.
     */
    public function next(RunModel $run): bool;

    /**
     * Whether a failed rollback should halt the unwind rather than push past it.
     */
    public function halts(RunStepModel $rollbackStep): bool;
}
