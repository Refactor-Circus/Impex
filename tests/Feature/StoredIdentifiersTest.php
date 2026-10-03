<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use JayI\Impex\Domains\Artifact\Models\ArtifactModel;
use JayI\Impex\Domains\Batch\Models\BatchItemModel;
use JayI\Impex\Domains\Batch\Models\BatchModel;
use JayI\Impex\Domains\Flow\Models\FlowOverrideModel;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\RunTrigger;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Domains\Signal\Models\SignalModel;
use JayI\Impex\Domains\Signal\Models\TimerModel;
use JayI\Impex\Jobs\DriveRun;
use JayI\Impex\Tests\Fixtures\LinearFlow;

/*
 * The models moved into their domains, but values written under their old
 * class names - a polymorphic `*_type` column, an audit subject - must keep
 * resolving, and new rows must keep writing the same value.
 */

it('keeps the class names the models were stored under before they moved', function (string $old, string $model): void {
    expect(Relation::getMorphedModel($old))->toBe($model)
        ->and((new $model)->getMorphClass())->toBe($old);
})->with([
    ['JayI\\Impex\\Models\\Artifact', ArtifactModel::class],
    ['JayI\\Impex\\Models\\Batch', BatchModel::class],
    ['JayI\\Impex\\Models\\BatchItem', BatchItemModel::class],
    ['JayI\\Impex\\Models\\FlowOverride', FlowOverrideModel::class],
    ['JayI\\Impex\\Models\\Message', MessageModel::class],
    ['JayI\\Impex\\Models\\Run', RunModel::class],
    ['JayI\\Impex\\Models\\RunOwner', RunOwnerModel::class],
    ['JayI\\Impex\\Models\\RunStep', RunStepModel::class],
    ['JayI\\Impex\\Models\\Signal', SignalModel::class],
    ['JayI\\Impex\\Models\\Timer', TimerModel::class],
]);

it('resolves a run owner stored under an old class name', function (): void {
    $run = RunModel::query()->create([
        'flow' => 'linear',
        'flow_class' => LinearFlow::class,
        'status' => RunStatus::Running,
        'trigger' => RunTrigger::Api,
    ]);

    $owner = RunOwnerModel::query()->create([
        'run_id' => $run->getKey(),
        'owner_type' => 'JayI\\Impex\\Models\\Run',
        'owner_id' => (string) $run->getKey(),
        'role' => 'owner',
    ]);

    expect($owner->fresh()?->owner)->toBeInstanceOf(RunModel::class)
        ->and($owner->fresh()?->owner?->is($run))->toBeTrue();
});

it('keeps the queued job class names, so jobs already on the queue still run', function (): void {
    // A DriveRun serialized before the reorganisation.
    $job = unserialize('O:24:"JayI\\Impex\\Jobs\\DriveRun":1:{s:5:"runId";s:3:"abc";}');

    expect($job)->toBeInstanceOf(DriveRun::class)
        ->and($job->runId)->toBe('abc');
});
