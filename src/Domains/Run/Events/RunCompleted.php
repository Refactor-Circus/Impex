<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Events;

final readonly class RunCompleted
{
    public function __construct(public string $runId) {}
}
