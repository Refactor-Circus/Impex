<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The RunOwner `creating` Eloquent event.
 */
final class RunOwnerCreatingEvent implements ModelLifecycleEvent
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
        return 'creating';
    }
}
