<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Artifact\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ModelLifecycleEvent;
use RefactorCircus\Impex\Domains\Artifact\Models\ArtifactModel;

/**
 * The Artifact `updating` Eloquent event.
 */
final class ArtifactUpdatingEvent implements ModelLifecycleEvent
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
        return 'updating';
    }
}
