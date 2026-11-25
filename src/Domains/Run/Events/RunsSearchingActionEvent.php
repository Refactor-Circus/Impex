<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Data\SearchRunsData;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * Runs are about to be searched.
 */
final class RunsSearchingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public SearchRunsData $data,
        public ?Model $viewer = null,
    ) {}
}
