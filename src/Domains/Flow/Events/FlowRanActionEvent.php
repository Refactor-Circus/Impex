<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Flow\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Impex\Contracts\ActionFinishedEvent;
use JayI\Impex\Domains\Run\Models\RunModel;

/**
 * A flow's run was accepted, and driven to completion when the caller asked to wait.
 */
final class FlowRanActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public RunModel $run,
    ) {}
}
