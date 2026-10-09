<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use RefactorCircus\Impex\Domains\Run\Events\RunShowingActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunShownActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

final class ShowRunAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    public function execute(RunModel $run): RunModel
    {
        RunShowingActionEvent::dispatch($run);

        $result = $this->perform($run);

        RunShownActionEvent::dispatch($result);

        return $result;
    }

    private function perform(RunModel $run): RunModel
    {
        return $run->load(['owners', 'forwardSteps']);
    }
}
