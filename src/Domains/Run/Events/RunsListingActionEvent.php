<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Data\ListRunsData;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * Runs are about to be listed.
 */
final class RunsListingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ListRunsData $filters,
        public ?Model $viewer = null,
    ) {}
}
