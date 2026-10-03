<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Impex\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;

/**
 * The RunOwner `saving` Eloquent event.
 */
final class RunOwnerSavingEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public RunOwnerModel $runOwner) {}

    public function model(): Model
    {
        return $this->runOwner;
    }

    public function hook(): string
    {
        return 'saving';
    }
}
