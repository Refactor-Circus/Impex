<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Tests\Fixtures\LinearFlow;
use Workbench\App\Models\User;

/*
 * The run link shows only when every condition holds: the row is a saved
 * run, Impex's Atrium plugin is authorized for the request, the viewer may
 * view the run (operators included) and the run page's route exists. Each
 * case below makes exactly one of them false.
 */

beforeEach(function (): void {
    $this->ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
    $this->bob = User::forceCreate(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'x']);
});

function visibilityRun(string $flow, ?User $owner = null): RunModel
{
    $run = RunModel::query()->create([
        'flow' => $flow,
        'flow_class' => LinearFlow::class,
        'status' => RunStatus::Completed,
        'trigger' => $owner === null ? RunTrigger::Schedule : RunTrigger::Api,
    ]);

    if ($owner !== null) {
        $run->owners()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'role' => 'owner',
        ]);
    }

    return $run;
}

function runLinkSection(): SectionDefinition
{
    return app(ScreenExtensions::class)->sections('showroom.transfers', 'row.actions')[0];
}

/**
 * Whether the run link would show on this row for the user, asked as the
 * host's screen asks it.
 */
function runLinkShows(mixed $record, ?User $user): bool
{
    $request = Request::create('/test-host/showroom.transfers');
    $request->setUserResolver(fn (): ?Authenticatable => $user);

    return runLinkSection()->allows($request, $record, Screen::standard('showroom.transfers', Screen::LIST), null);
}

/**
 * The queries the test host makes while rendering these rows, and the run
 * links it renders.
 *
 * @param  list<RunModel>  $rows
 * @return array{queries: int, links: int}
 */
function hostRender(array $rows): array
{
    config()->set('test-host.rows', $rows);

    // A new request: Atrium's per-request answers start over, as in an app.
    app()->forgetScopedInstances();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $content = (string) test()->get(route('test-host', 'showroom.transfers'))->assertOk()->getContent();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return ['queries' => $queries, 'links' => substr_count($content, 'data-testid="impex-run-link"')];
}

/**
 * Render one run, then all of them, after a first request warms anything
 * the application does once.
 *
 * @param  list<RunModel>  $runs
 */
function expectConstantQueries(array $runs): void
{
    hostRender([$runs[0]]);

    $one = hostRender([$runs[0]]);
    $all = hostRender($runs);

    expect($one['links'])->toBe(1)
        ->and($all['links'])->toBe(count($runs))
        ->and($all['queries'])->toBe($one['queries']);
}

it('shows on a saved run the viewer may view', function (): void {
    expect(runLinkShows(visibilityRun('nightly'), $this->ann))->toBeTrue();
});

it('hides on a row that is not a run, without a query', function (): void {
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect(runLinkShows($this->bob, $this->ann))->toBeFalse()
        ->and($queries)->toBe(0);
});

it('hides on an unsaved run', function (): void {
    $run = new RunModel(['flow' => 'nightly']);

    expect(runLinkShows($run, $this->ann))->toBeFalse();
});

it('hides while the plugin is not authorized for the request', function (): void {
    config()->set('impex.atrium.features', ['impex-ui']);
    Atrium::resolveFeaturesUsing(fn (string $feature, Request $request): bool => $request->user()?->getAuthIdentifier() !== $this->ann->getKey());

    $run = visibilityRun('nightly');

    expect(runLinkShows($run, $this->ann))->toBeFalse()
        ->and(runLinkShows($run, $this->bob))->toBeTrue();
});

it('hides when screen access refuses the run', function (): void {
    config()->set('impex.authorization', true);

    $run = visibilityRun('nightly', $this->bob);

    expect(runLinkShows($run, $this->ann))->toBeFalse()
        ->and(runLinkShows($run, $this->bob))->toBeTrue();
});

it('hides when the run page route is absent', function (): void {
    $routes = new RouteCollection;

    foreach (Route::getRoutes() as $route) {
        if ($route->getName() !== 'atrium.impex.runs.show') {
            $routes->add($route);
        }
    }

    Route::setRoutes($routes);

    expect(Route::has('atrium.impex.runs.show'))->toBeFalse()
        ->and(runLinkShows(visibilityRun('nightly'), $this->ann))->toBeFalse();
});

it('asks whether the plugin is authorized once per request', function (): void {
    $asked = 0;

    config()->set('impex.atrium.features', ['impex-ui']);
    Atrium::resolveFeaturesUsing(function (string $feature, Request $request) use (&$asked): bool {
        $asked++;

        return true;
    });

    $request = Request::create('/test-host/showroom.transfers');
    $request->setUserResolver(fn (): User => $this->ann);
    $screen = Screen::standard('showroom.transfers', Screen::LIST);

    foreach (range(1, 5) as $index) {
        expect(runLinkSection()->allows($request, visibilityRun('flow-'.$index), $screen, null))->toBeTrue();
    }

    // Once for the plugin's one feature, not once per row.
    expect($asked)->toBe(1);
});

it('makes a constant number of queries however many runs the host lists', function (): void {
    $runs = array_map(fn (int $index): RunModel => visibilityRun('flow-'.$index, $this->ann), range(1, 25));

    $this->actingAs($this->ann);

    expectConstantQueries($runs);
});

it('makes a constant number of queries for an operator with authorization on', function (): void {
    config()->set('impex.authorization', true);
    config()->set('impex.atrium.show_all', true);

    $runs = array_map(fn (int $index): RunModel => visibilityRun('flow-'.$index, $this->bob), range(1, 25));

    $this->actingAs($this->ann);

    expectConstantQueries($runs);
});

it('makes a constant number of queries for an owner when the host loads the owners', function (): void {
    config()->set('impex.authorization', true);

    foreach (range(1, 25) as $index) {
        visibilityRun('flow-'.$index, $this->ann);
    }

    $this->actingAs($this->ann);

    expectConstantQueries(RunModel::query()->with('owners')->get()->all());
});

it('loads no relations on the rows when asking the view policy', function (): void {
    config()->set('impex.authorization', true);

    $runs = array_map(fn (int $index): RunModel => visibilityRun('flow-'.$index, $this->ann), range(1, 25));
    $runs = RunModel::query()->whereKey(array_map(fn (RunModel $run): mixed => $run->getKey(), $runs))->get()->all();

    config()->set('test-host.rows', $runs);

    $content = (string) $this->actingAs($this->ann)->get(route('test-host', 'showroom.transfers'))->assertOk()->getContent();

    expect(substr_count($content, 'data-testid="impex-run-link"'))->toBe(25);

    foreach ($runs as $run) {
        expect($run->getRelations())->toBe([]);
    }
});
