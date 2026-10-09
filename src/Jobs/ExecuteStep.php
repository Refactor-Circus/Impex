<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Services\Engine;
use RefactorCircus\Impex\Support\Concerns\UsesConfiguredMiddleware;

/**
 * Claims one step under lease, runs it, and records the outcome.
 *
 * Carries identifiers only. The step's arguments are read from the database or
 * the artifact disk inside the engine.
 */
final class ExecuteStep implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesConfiguredMiddleware;

    public function __construct(
        public readonly string $runId,
        public readonly string $phase,
        public readonly int $sequence,
    ) {}

    public function handle(Engine $engine): void
    {
        $engine->executeStep($this->runId, $this->phase, $this->sequence);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['impex', 'run:'.$this->runId, 'step:'.$this->phase.':'.$this->sequence];
    }
}
