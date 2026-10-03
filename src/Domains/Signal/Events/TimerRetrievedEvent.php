<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Signal\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Impex\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Signal\Models\TimerModel;

/**
 * The Timer `retrieved` Eloquent event.
 */
final class TimerRetrievedEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public TimerModel $timer) {}

    public function model(): Model
    {
        return $this->timer;
    }

    public function hook(): string
    {
        return 'retrieved';
    }
}
