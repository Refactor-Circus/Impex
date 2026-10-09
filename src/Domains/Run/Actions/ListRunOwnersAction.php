<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use Illuminate\Database\Eloquent\Collection;
use RefactorCircus\Impex\Domains\Run\Events\RunOwnersListedActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunOwnersListingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;

final class ListRunOwnersAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * @return Collection<int, RunOwnerModel>
     */
    public function execute(RunModel $run): Collection
    {
        RunOwnersListingActionEvent::dispatch($run);

        $result = $this->perform($run);

        RunOwnersListedActionEvent::dispatch($run, $result);

        return $result;
    }

    /**
     * @return Collection<int, RunOwnerModel>
     */
    private function perform(RunModel $run): Collection
    {
        return $run->owners()->get();
    }
}
