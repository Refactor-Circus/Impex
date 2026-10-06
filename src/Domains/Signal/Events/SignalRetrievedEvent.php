<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Signal\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Signal\Models\SignalModel;

/**
 * The Signal `retrieved` Eloquent event.
 */
final class SignalRetrievedEvent implements ModelLifecycleEvent
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
        return 'retrieved';
    }
}
