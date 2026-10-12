<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use RefactorCircus\Impex\Domains\Run\Data\CancelRunData;
use RefactorCircus\Impex\Domains\Run\Events\RunCancelledActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunCancellingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Impex;

final class CancelRunAction
{
    public function __construct(private readonly Impex $impex) {}

    public function execute(RunModel $run, CancelRunData $data = new CancelRunData): RunModel
    {
        RunCancellingActionEvent::dispatch($run, $data->reason);

        $result = $this->perform($run, $data);

        RunCancelledActionEvent::dispatch($result);

        return $result;
    }

    private function perform(RunModel $run, CancelRunData $data): RunModel
    {
        return $this->impex->cancel($run, $data->reason);
    }
}
