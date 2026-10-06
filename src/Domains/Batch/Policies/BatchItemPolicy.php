<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Batch\Policies;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Domains\Batch\Models\BatchItemModel;
use JayI\Impex\Domains\Batch\Models\BatchModel;
use JayI\Impex\Support\Policies\Policy;

/**
 * Items follow their batch, which follows its run: reading them needs `view`
 * on the batch, and no ability changes them.
 */
class BatchItemPolicy extends Policy
{
    public function viewAny(Model $user, BatchModel $batch): bool
    {
        return $this->allowsOn($user, 'view', $batch);
    }

    public function view(Model $user, BatchItemModel $item): bool
    {
        return $this->allowsOn($user, 'view', $item->batch);
    }
}
