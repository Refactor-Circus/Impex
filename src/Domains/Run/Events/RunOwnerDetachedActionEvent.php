<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;

/**
 * An owner was detached from a run. The row is gone, so its fields are kept.
 */
final class RunOwnerDetachedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public RunModel $run,
        public string $ownerType,
        public string $ownerId,
        public string $role,
    ) {}
}
