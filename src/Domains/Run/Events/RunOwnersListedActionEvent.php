<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;

/**
 * A run's owners were listed.
 */
final class RunOwnersListedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Collection<int, RunOwnerModel>  $owners
     */
    public function __construct(
        public RunModel $run,
        public Collection $owners,
    ) {}
}
