<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

/**
 * A run was shown with its owners and steps.
 */
final class RunShownActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public RunModel $run,
    ) {}
}
