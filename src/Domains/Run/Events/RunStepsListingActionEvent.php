<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionStartingEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

/**
 * A run's steps are about to be read.
 */
final class RunStepsListingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public RunModel $run,
        public array $filters,
    ) {}
}
