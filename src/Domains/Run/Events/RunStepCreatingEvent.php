<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ModelLifecycleEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunStepModel;

/**
 * The RunStep `creating` Eloquent event.
 */
final class RunStepCreatingEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public RunStepModel $runStep) {}

    public function model(): Model
    {
        return $this->runStep;
    }

    public function hook(): string
    {
        return 'creating';
    }
}
