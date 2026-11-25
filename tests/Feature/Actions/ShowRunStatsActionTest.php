<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Run\Actions\ShowRunStatsAction;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Events\RunStatsShowingActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunStatsShownActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use Workbench\App\Models\User;

/**
 * A run in the given status, owned by the given user when one is passed.
 *
 * @param  array<string, mixed>  $attributes
 */
function runForStats(RunStatus $status, ?User $owner = null, array $attributes = []): RunModel
{
    $run = RunModel::factory()->create(['status' => $status, ...$attributes]);

    if ($owner !== null) {
        $run->owners()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'role' => 'user',
        ]);
    }

    return $run;
}

it('counts runs for every status, with zero for a status that has none', function (): void {
    runForStats(RunStatus::Running);
    runForStats(RunStatus::Running);
    runForStats(RunStatus::Failed);

    $stats = app(ShowRunStatsAction::class)->execute(viewer: null, withMessages: true);

    expect(array_keys($stats->byStatus))->toBe(array_column(RunStatus::cases(), 'value'))
        ->and($stats->byStatus)->toBe([
            'pending' => 0,
            'running' => 2,
            'waiting' => 0,
            'rolling_back' => 0,
            'completed' => 0,
            'failed' => 1,
            'cancelled' => 0,
        ]);
});

it('counts as active exactly the runs the active scope matches', function (): void {
    foreach (RunStatus::cases() as $status) {
        runForStats($status);
    }

    $stats = app(ShowRunStatsAction::class)->execute(viewer: null, withMessages: true);

    expect($stats->active)->toBe(RunModel::query()->active()->count())
        ->and($stats->active)->toBe(4);
});

it('lists at most five failed runs, the latest to finish first', function (): void {
    $failed = [];

    foreach (range(1, 7) as $minutesAgo) {
        $failed[$minutesAgo] = runForStats(RunStatus::Failed, attributes: ['finished_at' => Carbon::now()->subMinutes($minutesAgo)]);
    }

    // A run that finished more recently but did not fail is not a failure.
    runForStats(RunStatus::Completed, attributes: ['finished_at' => Carbon::now()]);

    $stats = app(ShowRunStatsAction::class)->execute(viewer: null, withMessages: true);

    expect($stats->recentFailures->modelKeys())->toBe([
        $failed[1]->getKey(),
        $failed[2]->getKey(),
        $failed[3]->getKey(),
        $failed[4]->getKey(),
        $failed[5]->getKey(),
    ]);
});

it('counts inbound and outbound messages from the last 24 hours only', function (): void {
    $this->freezeTime();

    MessageModel::factory()->create(['direction' => Direction::Inbound, 'occurred_at' => Carbon::now()->subHour()]);
    MessageModel::factory()->create(['direction' => Direction::Inbound, 'occurred_at' => Carbon::now()->subHours(23)]);
    MessageModel::factory()->create(['direction' => Direction::Inbound, 'occurred_at' => Carbon::now()->subHours(25)]);
    MessageModel::factory()->create(['direction' => Direction::Outbound, 'occurred_at' => Carbon::now()->subMinutes(5)]);
    MessageModel::factory()->create(['direction' => Direction::Outbound, 'occurred_at' => Carbon::now()->subDays(2)]);

    $stats = app(ShowRunStatsAction::class)->execute(viewer: null, withMessages: true);

    expect($stats->messages)->toBe(['window_hours' => 24, 'inbound' => 2, 'outbound' => 1]);
});

it('covers only the runs a viewer owns and those runs\' messages', function (): void {
    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
    $bob = User::forceCreate(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'x']);

    $annRunning = runForStats(RunStatus::Running, $ann);
    $annFailed = runForStats(RunStatus::Failed, $ann, ['finished_at' => Carbon::now()->subHour()]);
    $bobRunning = runForStats(RunStatus::Running, $bob);
    runForStats(RunStatus::Failed, $bob, ['finished_at' => Carbon::now()]);

    MessageModel::factory()->create(['direction' => Direction::Inbound, 'run_id' => $annRunning->getKey()]);
    MessageModel::factory()->create(['direction' => Direction::Outbound, 'run_id' => $annFailed->getKey()]);
    MessageModel::factory()->create(['direction' => Direction::Inbound, 'run_id' => $bobRunning->getKey()]);
    // Traffic not bound to a run belongs to nobody.
    MessageModel::factory()->create(['direction' => Direction::Outbound]);

    $mine = app(ShowRunStatsAction::class)->execute(viewer: $ann, withMessages: true);

    expect($mine->byStatus['running'])->toBe(1)
        ->and($mine->byStatus['failed'])->toBe(1)
        ->and($mine->active)->toBe(1)
        ->and($mine->recentFailures->modelKeys())->toBe([$annFailed->getKey()])
        ->and($mine->messages)->toBe(['window_hours' => 24, 'inbound' => 1, 'outbound' => 1]);

    // With no viewer, as when authorization is off, nothing is scoped.
    $all = app(ShowRunStatsAction::class)->execute(viewer: null, withMessages: true);

    expect($all->byStatus['running'])->toBe(2)
        ->and($all->byStatus['failed'])->toBe(2)
        ->and($all->active)->toBe(2)
        ->and($all->recentFailures)->toHaveCount(2)
        ->and($all->messages)->toBe(['window_hours' => 24, 'inbound' => 2, 'outbound' => 2]);
});

it('leaves the message counts out when they are not asked for', function (): void {
    MessageModel::factory()->create(['direction' => Direction::Inbound]);

    $stats = app(ShowRunStatsAction::class)->execute(viewer: null, withMessages: false);

    expect($stats->messages)->toBeNull();
});

it('dispatches the starting and finished events', function (): void {
    Event::fake([RunStatsShowingActionEvent::class, RunStatsShownActionEvent::class]);

    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);

    $stats = app(ShowRunStatsAction::class)->execute(viewer: $ann, withMessages: false);

    Event::assertDispatched(
        RunStatsShowingActionEvent::class,
        fn (RunStatsShowingActionEvent $event): bool => $event->viewer?->is($ann) === true && $event->withMessages === false,
    );
    Event::assertDispatched(
        RunStatsShownActionEvent::class,
        fn (RunStatsShownActionEvent $event): bool => $event->stats === $stats && $event->viewer?->is($ann) === true,
    );
});
