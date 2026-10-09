<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Batch\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Batch\Models\BatchModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The Batch `created` Eloquent event.
 */
final class BatchCreatedEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public BatchModel $batch) {}

    public function model(): Model
    {
        return $this->batch;
    }

    public function hook(): string
    {
        return 'created';
    }
}
