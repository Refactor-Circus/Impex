<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;

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
