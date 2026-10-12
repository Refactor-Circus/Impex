<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use Illuminate\Support\Facades\DB;
use RefactorCircus\Impex\Domains\Run\Events\RunOwnerDetachedActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunOwnerDetachingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;

final class DetachRunOwnerAction
{
    public function execute(RunModel $run, RunOwnerModel $owner): void
    {
        $ownerType = $owner->owner_type;
        $ownerId = $owner->owner_id;
        $role = $owner->role;

        RunOwnerDetachingActionEvent::dispatch($run, $owner);

        $this->perform($run, $owner);

        RunOwnerDetachedActionEvent::dispatch($run, $ownerType, $ownerId, $role);
    }

    private function perform(RunModel $run, RunOwnerModel $owner): void
    {
        if ($owner->run_id !== $run->getKey()) {
            abort(404);
        }

        DB::transaction(fn (): ?bool => $owner->delete());
    }
}
