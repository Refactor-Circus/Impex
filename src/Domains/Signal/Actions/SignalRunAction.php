<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Actions;

use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Signal\Data\SignalRunData;
use RefactorCircus\Impex\Domains\Signal\Events\RunSignalledActionEvent;
use RefactorCircus\Impex\Domains\Signal\Events\RunSignallingActionEvent;
use RefactorCircus\Impex\Domains\Signal\Models\SignalModel;
use RefactorCircus\Impex\Impex;

final class SignalRunAction
{
    public function __construct(private readonly Impex $impex) {}

    /**
     * Deliver the signal.
     *
     * Returns null when `if_running` was set and the run had already finished —
     * a no-op the caller asked for, rather than a conflict.
     */
    public function execute(RunModel $run, SignalRunData $data): ?SignalModel
    {
        RunSignallingActionEvent::dispatch($run, $data);

        $result = $this->perform($run, $data);

        RunSignalledActionEvent::dispatch($run, $result);

        return $result;
    }

    /**
     * Deliver the signal.
     *
     * Returns null when `if_running` was set and the run had already finished —
     * a no-op the caller asked for, rather than a conflict.
     */
    private function perform(RunModel $run, SignalRunData $data): ?SignalModel
    {
        if ($data->ifRunning) {
            return $this->impex->signalIfRunning($run, $data->name, $data->payload, $data->idempotencyKey)
                ? $run->signals()->where('name', $data->name)->latest('delivered_at')->first()
                : null;
        }

        return $this->impex->signal($run, $data->name, $data->payload, $data->idempotencyKey);
    }
}
