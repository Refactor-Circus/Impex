<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Signal\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Signal\Models\SignalModel;

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
