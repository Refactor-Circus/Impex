<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Batch\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ModelLifecycleEvent;
use RefactorCircus\Impex\Domains\Batch\Models\BatchItemModel;

/**
 * The BatchItem `updating` Eloquent event.
 */
final class BatchItemUpdatingEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public BatchItemModel $batchItem) {}

    public function model(): Model
    {
        return $this->batchItem;
    }

    public function hook(): string
    {
        return 'updating';
    }
}
