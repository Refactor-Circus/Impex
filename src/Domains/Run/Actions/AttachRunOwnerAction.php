<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use Illuminate\Support\Facades\DB;
use RefactorCircus\Impex\Domains\Run\Data\AttachRunOwnerData;
use RefactorCircus\Impex\Domains\Run\Events\RunOwnerAttachedActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunOwnerAttachingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;

final class AttachRunOwnerAction
{
    public function execute(RunModel $run, AttachRunOwnerData $data): RunOwnerModel
    {
        RunOwnerAttachingActionEvent::dispatch($run, $data);

        $result = $this->perform($run, $data);

        RunOwnerAttachedActionEvent::dispatch($run, $result);

        return $result;
    }

    private function perform(RunModel $run, AttachRunOwnerData $data): RunOwnerModel
    {
        /** @var RunOwnerModel $owner */
        $owner = DB::transaction(fn (): RunOwnerModel => $run->owners()->firstOrCreate($data->toArray()));

        return $owner;
    }
}
