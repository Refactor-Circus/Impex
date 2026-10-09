<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ModelLifecycleEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

/**
 * The Run `saving` Eloquent event.
 */
final class RunSavingEvent implements ModelLifecycleEvent
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
        return 'saving';
    }
}
