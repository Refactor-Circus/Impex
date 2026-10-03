<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Artifact\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Impex\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Artifact\Models\ArtifactModel;

/**
 * The Artifact `created` Eloquent event.
 */
final class ArtifactCreatedEvent implements ModelLifecycleEvent
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
        return 'created';
    }
}
