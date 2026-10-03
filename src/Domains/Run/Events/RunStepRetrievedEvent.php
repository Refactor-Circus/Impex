<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Impex\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Run\Models\RunStepModel;

/**
 * The RunStep `retrieved` Eloquent event.
 */
final class RunStepRetrievedEvent implements ModelLifecycleEvent
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
        return 'retrieved';
    }
}
