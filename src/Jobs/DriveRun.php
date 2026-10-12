<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Services\Engine;
use RefactorCircus\Impex\Support\Concerns\UsesConfiguredMiddleware;

/**
 * Replays a run and schedules whatever it reaches that is not yet recorded.
 *
 * Carries a ULID and nothing else, so the queue message can never approach
 * SQS's 256KB limit however large the run's payloads are.
 *
 * At most one waits on the queue per run: it replays the whole history when
 * it starts, so a second one queued behind it would find nothing new. Once it
 * starts, another may be queued; one that arrives mid-drive leaves the dirty
 * marker, and the running drive looks again.
 */
final class DriveRun implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesConfiguredMiddleware;

    /**
     * Seconds a waiting drive holds its place; the router sets it from
     * `impex.limits.lock_seconds`.
     */
    public int $uniqueFor = 120;

    public function __construct(public readonly string $runId) {}

    public function uniqueId(): string
    {
        return $this->runId;
    }

    /**
     * How long a waiting drive holds its place. One lost on the way to a
     * worker must not stop the run being driven: once this lapses, the
     * next step or tick queues another.
     */
    public function uniqueFor(): int
    {
        return $this->uniqueFor;
    }

    public function handle(Engine $engine): void
    {
        $engine->drive($this->runId);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['impex', 'run:'.$this->runId];
    }
}
