<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Signal\Data\SignalRunData;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * A signal is about to be sent to a run.
 */
final class RunSignallingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public RunModel $run,
        public SignalRunData $data,
    ) {}
}
