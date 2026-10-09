<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ModelLifecycleEvent;
use RefactorCircus\Impex\Domains\Signal\Models\SignalModel;

/**
 * The Signal `updating` Eloquent event.
 */
final class SignalUpdatingEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public SignalModel $signal) {}

    public function model(): Model
    {
        return $this->signal;
    }

    public function hook(): string
    {
        return 'updating';
    }
}
