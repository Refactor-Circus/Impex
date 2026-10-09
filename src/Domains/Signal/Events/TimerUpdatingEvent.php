<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Signal\Models\TimerModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The Timer `updating` Eloquent event.
 */
final class TimerUpdatingEvent implements ModelLifecycleEvent
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
        return 'updating';
    }
}
