<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use JayI\Impex\Domains\Batch\Models\BatchItemModel;
use JayI\Impex\Domains\Batch\Models\BatchModel;
use JayI\Impex\Domains\Batch\Services\BatchRunner;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\RunTrigger;
use JayI\Impex\Domains\Run\Enums\StepStatus;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Services\Sweeper;
use JayI\Impex\Jobs\ProcessBatchItem;
use JayI\Impex\Tests\Fixtures\BatchFlow;
use JayI\Impex\Tests\Fixtures\EnrichItem;
use JayI\Impex\Tests\Fixtures\PagedSource;

beforeEach(function (): void {
    Queue::fake();
});

/**
 * An item whose worker died mid-attempt: running, its lease lapsed.
 */
function strandedItem(int $attempts, int $maxAttempts): BatchItemModel
{
    $run = RunModel::query()->create([
        'flow' => 'batching',
        'flow_class' => BatchFlow::class,
        'status' => RunStatus::Running,
        'trigger' => RunTrigger::Code,
    ]);

    $batch = BatchModel::query()->create([
        'run_id' => $run->id,
        'step_id' => strtolower((string) Str::ulid()),
        'source' => PagedSource::class,
        'action' => EnrichItem::class,
        'max_attempts' => $maxAttempts,
        'seeded' => true,
        'total' => 1,
    ]);

    return BatchItemModel::query()->create([
        'batch_id' => $batch->id,
        'item_key' => 'P0-0',
        'payload' => ['value' => ['key' => 'P0-0', 'n' => 0]],
        'status' => StepStatus::Running,
        'attempts' => $attempts,
        'lease_token' => 'dead-worker',
        'leased_until' => now()->subMinute(),
    ]);
}

it('takes back an item whose worker died and runs it again', function (): void {
    $item = strandedItem(attempts: 1, maxAttempts: 3);

    $report = app(Sweeper::class)->sweep();

    expect($item->refresh()->status)->toBe(StepStatus::Pending)
        ->and($item->leased_until)->toBeNull()
        ->and($item->error['message'] ?? '')->toContain('lease lapsed')
        ->and($report->leases)->toBe(1);

    Queue::assertPushed(ProcessBatchItem::class, fn (ProcessBatchItem $job): bool => $job->itemId === $item->id);
});

it('fails an item whose worker keeps dying once it is out of attempts', function (): void {
    $item = strandedItem(attempts: 3, maxAttempts: 3);

    app(BatchRunner::class)->reclaimLeases();

    expect($item->refresh()->status)->toBe(StepStatus::Failed)
        ->and(BatchModel::query()->sole()->failed)->toBe(1);

    Queue::assertNotPushed(ProcessBatchItem::class);
});

it('leaves an item whose lease is still live', function (): void {
    $item = strandedItem(attempts: 1, maxAttempts: 3);
    $item->update(['leased_until' => now()->addMinutes(5)]);

    expect(app(BatchRunner::class)->reclaimLeases())->toBe(0)
        ->and($item->refresh()->status)->toBe(StepStatus::Running);
});

it('gives its item up at once when the worker times it out', function (): void {
    $item = strandedItem(attempts: 0, maxAttempts: 3);
    $item->update(['status' => StepStatus::Pending, 'lease_token' => null, 'leased_until' => null]);

    $job = new ProcessBatchItem($item->id);
    $runner = app(BatchRunner::class);

    // Interrupted while its action runs, as the worker's timeout handler does.
    app()->bind(EnrichItem::class, fn (): object => new class($job)
    {
        public function __construct(private readonly ProcessBatchItem $job) {}

        /**
         * @param  array<string, mixed>  $item
         * @return array<string, mixed>
         */
        public function execute(array $item): array
        {
            $this->job->interrupted(SIGALRM);

            return $item;
        }
    });

    $job->handle($runner);

    expect($item->refresh()->status)->toBe(StepStatus::Pending)
        ->and($item->attempts)->toBe(1)
        ->and($item->error['message'] ?? '')->toContain('timeout');

    Queue::assertPushed(ProcessBatchItem::class);
})->skip(! defined('SIGALRM'), 'Needs pcntl.');
