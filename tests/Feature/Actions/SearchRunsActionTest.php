<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RefactorCircus\Impex\Domains\Run\Actions\SearchRunsAction;
use RefactorCircus\Impex\Domains\Run\Data\SearchRunsData;
use RefactorCircus\Impex\Domains\Run\Events\RunsSearchedActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunsSearchingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use Workbench\App\Models\User;

/**
 * A run of the given flow, owned by the given user when one is passed.
 *
 * @param  array<string, mixed>  $attributes
 */
function runForSearch(string $flow, ?User $owner = null, array $attributes = []): RunModel
{
    $run = RunModel::factory()->create(['flow' => $flow, ...$attributes]);

    if ($owner !== null) {
        $run->owners()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'role' => 'user',
        ]);
    }

    return $run;
}

/**
 * The ids of the runs a search finds, in the order it returns them.
 *
 * @return array<int, string>
 */
function searchRuns(string $q, int $limit = 5, ?User $viewer = null): array
{
    return app(SearchRunsAction::class)
        ->execute(new SearchRunsData(q: $q, limit: $limit), $viewer)
        ->modelKeys();
}

it('finds runs whose flow contains the query', function (): void {
    $orders = runForSearch('sync-orders');
    $reorders = runForSearch('nightly-orders-export');
    runForSearch('sync-customers');

    expect(searchRuns('orders'))->toEqualCanonicalizing([$orders->getKey(), $reorders->getKey()]);
});

it('finds runs whose id starts with the query, whatever its case', function (): void {
    $wanted = runForSearch('example', attributes: ['id' => '01jaaaaaaaaaaaaaaaaaaaaaaa']);
    runForSearch('example', attributes: ['id' => '01jbbbbbbbbbbbbbbbbbbbbbbb']);
    // The query in the middle of an id is not a prefix.
    runForSearch('example', attributes: ['id' => '01kzz01jaaaaaaaaaaaaaaaaaa']);

    expect(searchRuns('01jaaa'))->toBe([$wanted->getKey()])
        ->and(searchRuns('01JAAA'))->toBe([$wanted->getKey()]);
});

it('matches %, _ and backslash in the query literally', function (): void {
    $underscore = runForSearch('orders_sync');
    runForSearch('ordersXsync');
    $percent = runForSearch('100%-done');
    runForSearch('100-done');
    $backslash = runForSearch('App\\Orders');
    runForSearch('App/Orders');

    expect(searchRuns('rs_s'))->toBe([$underscore->getKey()])
        ->and(searchRuns('100%'))->toBe([$percent->getKey()])
        ->and(searchRuns('p\\O'))->toBe([$backslash->getKey()])
        ->and(searchRuns('%'))->toBe([$percent->getKey()])
        ->and(searchRuns('_'))->toBe([$underscore->getKey()]);
});

it('lists the newest runs first, breaking ties by id', function (): void {
    $now = Carbon::now();

    $older = runForSearch('sync', attributes: ['id' => '01jzzzzzzzzzzzzzzzzzzzzzzz', 'created_at' => $now->copy()->subMinute()]);
    $tieLow = runForSearch('sync', attributes: ['id' => '01jaaaaaaaaaaaaaaaaaaaaaaa', 'created_at' => $now]);
    $tieHigh = runForSearch('sync', attributes: ['id' => '01jbbbbbbbbbbbbbbbbbbbbbbb', 'created_at' => $now]);

    expect(searchRuns('sync'))->toBe([$tieHigh->getKey(), $tieLow->getKey(), $older->getKey()]);
});

it('returns at most the limit, five by default', function (): void {
    foreach (range(1, 7) as $minutesAgo) {
        runForSearch('sync', attributes: ['created_at' => Carbon::now()->subMinutes($minutesAgo)]);
    }

    expect(new SearchRunsData(q: 'sync'))->limit->toBe(5)
        ->and(app(SearchRunsAction::class)->execute(new SearchRunsData(q: 'sync'), null))->toHaveCount(5)
        ->and(searchRuns('sync', limit: 2))->toHaveCount(2)
        ->and(searchRuns('sync', limit: 25))->toHaveCount(7);
});

it('searches only the runs a viewer owns', function (): void {
    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
    $bob = User::forceCreate(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'x']);

    $annRun = runForSearch('sync-orders', $ann);
    $bobRun = runForSearch('sync-orders', $bob);
    $nobodys = runForSearch('sync-orders');

    expect(searchRuns('orders', viewer: $ann))->toBe([$annRun->getKey()])
        // With no viewer, as when authorization is off, nothing is scoped.
        ->and(searchRuns('orders'))->toEqualCanonicalizing([$annRun->getKey(), $bobRun->getKey(), $nobodys->getKey()]);
});

it('dispatches the starting and finished events', function (): void {
    Event::fake([RunsSearchingActionEvent::class, RunsSearchedActionEvent::class]);

    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
    $data = new SearchRunsData(q: 'sync', limit: 3);

    $runs = app(SearchRunsAction::class)->execute($data, $ann);

    Event::assertDispatched(
        RunsSearchingActionEvent::class,
        fn (RunsSearchingActionEvent $event): bool => $event->data === $data && $event->viewer?->is($ann) === true,
    );
    Event::assertDispatched(
        RunsSearchedActionEvent::class,
        fn (RunsSearchedActionEvent $event): bool => $event->runs === $runs && $event->viewer?->is($ann) === true,
    );
});
