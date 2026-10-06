<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;

/**
 * An owner was attached to a run, or was already attached.
 */
final class RunOwnerAttachedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public RunModel $run,
        public RunOwnerModel $owner,
    ) {}
}
