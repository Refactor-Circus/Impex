<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionStartingEvent;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;

/**
 * A flow is about to be run. The run itself starts later, on the queue; RunStarted marks that.
 */
final class FlowRunningActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $slug,
        public array $data,
        public RunTrigger $trigger,
        public ?Model $owner = null,
    ) {}
}
