<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ModelLifecycleEvent;
use RefactorCircus\Impex\Domains\Flow\Models\FlowOverrideModel;

/**
 * The FlowOverride `saved` Eloquent event.
 */
final class FlowOverrideSavedEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public FlowOverrideModel $flowOverride) {}

    public function model(): Model
    {
        return $this->flowOverride;
    }

    public function hook(): string
    {
        return 'saved';
    }
}
