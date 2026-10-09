<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The Run `saved` Eloquent event.
 */
final class RunSavedEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public RunModel $run) {}

    public function model(): Model
    {
        return $this->run;
    }

    public function hook(): string
    {
        return 'saved';
    }
}
