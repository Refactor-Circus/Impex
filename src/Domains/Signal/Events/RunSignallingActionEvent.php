<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

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
