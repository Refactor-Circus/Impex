<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Artifact\Policies;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Domains\Artifact\Models\ArtifactModel;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Support\Policies\Policy;

/**
 * An artifact is a stored payload of a run, read with it and never edited.
 * One with no run has no owner, so it is denied.
 */
class ArtifactPolicy extends Policy
{
    public function view(Model $user, ArtifactModel $artifact): bool
    {
        $run = $artifact->run_id === null ? null : RunModel::query()->find($artifact->run_id);

        return $this->allowsOnRun($user, 'view', $run);
    }
}
