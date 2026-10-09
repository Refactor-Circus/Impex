<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunStepModel;

/**
 * A run's steps were read.
 */
final class RunStepsListedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Collection<int, RunStepModel>  $steps
     */
    public function __construct(
        public RunModel $run,
        public Collection $steps,
    ) {}
}
