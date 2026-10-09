<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Artifact\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Artifact\Models\ArtifactModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The Artifact `replicating` Eloquent event.
 */
final class ArtifactReplicatingEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ArtifactModel $artifact) {}

    public function model(): Model
    {
        return $this->artifact;
    }

    public function hook(): string
    {
        return 'replicating';
    }
}
