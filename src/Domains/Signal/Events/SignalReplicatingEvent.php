<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Signal\Models\SignalModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The Signal `replicating` Eloquent event.
 */
final class SignalReplicatingEvent implements ModelLifecycleEvent
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
        return 'replicating';
    }
}
