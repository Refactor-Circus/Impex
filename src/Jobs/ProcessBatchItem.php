<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Interruptible;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Batch\Services\BatchRunner;
use RefactorCircus\Impex\Support\Concerns\UsesConfiguredMiddleware;

/**
 * Runs one batch item's action.
 *
 * Carries an identifier only. Item state lives outside the replay log, so this
 * never touches a run's history however many items the batch holds.
 *
 * Told when the worker is about to kill it for running past its timeout, it
 * gives the item up at once, so the item is retried now instead of once its
 * lease lapses. Other signals are left alone: a worker shutting down
 * gracefully lets the item finish.
 */
final class ProcessBatchItem implements Interruptible, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesConfiguredMiddleware;

    private ?BatchRunner $runner = null;

    public function __construct(public readonly string $itemId) {}

    public function handle(BatchRunner $batches): void
    {
        $this->runner = $batches;

        $batches->processItem($this->itemId);
    }

    public function interrupted(int $signal): void
    {
        // SIGALRM exists only with pcntl, which a worker needs to time out at all.
        if (defined('SIGALRM') && $signal === SIGALRM) {
            $this->runner?->abandonRunning();
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['impex', 'batch-item:'.$this->itemId];
    }
}
