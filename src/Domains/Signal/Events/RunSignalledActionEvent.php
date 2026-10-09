<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Signal\Models\SignalModel;

/**
 * A signal was sent to a run. `signal` is null when an if-running signal found the run finished.
 */
final class RunSignalledActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public RunModel $run,
        public ?SignalModel $signal,
    ) {}
}
