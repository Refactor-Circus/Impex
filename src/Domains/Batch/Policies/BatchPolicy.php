<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Batch\Policies;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Domains\Batch\Models\BatchModel;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Support\Policies\Policy;

/**
 * A batch is one step of its run, so reading it needs `view` on the run and
 * no ability changes it.
 */
class BatchPolicy extends Policy
{
    public function viewAny(Model $user, RunModel $run): bool
    {
        return $this->allowsOnRun($user, 'view', $run);
    }

    public function view(Model $user, BatchModel $batch): bool
    {
        return $this->allowsOnRun($user, 'view', $batch->run);
    }
}
