<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Batch\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Batch\Models\BatchItemModel;

/**
 * The BatchItem `retrieved` Eloquent event.
 */
final class BatchItemRetrievedEvent implements ModelLifecycleEvent
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
        return 'retrieved';
    }
}
