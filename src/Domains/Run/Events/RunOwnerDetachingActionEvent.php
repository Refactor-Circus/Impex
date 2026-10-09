<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionStartingEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;

/**
 * An owner is about to be detached from a run.
 */
final class RunOwnerDetachingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public RunModel $run,
        public RunOwnerModel $owner,
    ) {}
}
