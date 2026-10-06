<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Run\Models\RunModel;

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
