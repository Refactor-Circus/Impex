<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Flow\Models\FlowOverrideModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The FlowOverride `updating` Eloquent event.
 */
final class FlowOverrideUpdatingEvent implements ModelLifecycleEvent
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
        return 'updating';
    }
}
