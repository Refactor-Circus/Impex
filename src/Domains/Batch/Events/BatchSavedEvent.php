<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Batch\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Impex\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Batch\Models\BatchModel;

/**
 * The Batch `saved` Eloquent event.
 */
final class BatchSavedEvent implements ModelLifecycleEvent
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
        return 'saved';
    }
}
