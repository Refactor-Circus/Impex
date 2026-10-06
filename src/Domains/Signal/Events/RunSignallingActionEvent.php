<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Signal\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionStartingEvent;
use JayI\Impex\Domains\Run\Models\RunModel;

/**
 * A signal is about to be sent to a run.
 */
final class RunSignallingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public RunModel $run,
        public array $data,
    ) {}
}
