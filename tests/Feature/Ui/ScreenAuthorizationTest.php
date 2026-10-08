<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use JayI\Atrium\Domains\Navigation\Data\NavItem;
use JayI\Atrium\Domains\Navigation\Services\NavigationRegistry;
use JayI\Atrium\Domains\Widgets\Data\WidgetDefinition;
use JayI\Impex\Atrium\ImpexPlugin;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\RunTrigger;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Tests\Fixtures\LinearFlow;
use JayI\Impex\Tests\Fixtures\SignalFlow;
use Workbench\App\Models\User;

/*
 * Each ability is granted on its own, keyed as "{ability} {Model}", so every
 * nav item and control can be shown to be gated by exactly the check its
 * action makes: the same ability, of the same subject, as the JSON API.
 */

beforeEach(function (): void {
    ValidateCsrfToken::except(['*']);

    // Atrium denies access outside local until a gate is defined.
    app()->detectEnvironment(fn (): string => 'local');

    config()->set('impex.authorization', true);
    config()->set('impex.flows', ['linear' => LinearFlow::class, 'signal' => SignalFlow::class]);

    $this->granted = [];

    // Answers only for Impex's models, so Atrium's own gate is untouched.
    Gate::before(function (mixed $user, string $ability, array $arguments): ?bool {
        $subject = $arguments[0] ?? null;
        $class = is_object($subject) ? $subject::class : $subject;

        if (! is_string($class) || ! preg_match('/^JayI\\\\Impex\\\\Domains\\\\\\w+\\\\Models\\\\(\\w+)Model$/', $class, $model)) {
            return null;
        }

        return in_array($ability.' '.$model[1], $this->granted, true);
    });

    $this->user = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
});

/**
 * @param  array<int, string>  $abilities
 */
function grant(array $abilities): void
{
    test()->granted = $abilities;
}

function ownedRun(User $owner, RunStatus $status = RunStatus::Running, string $flow = 'linear'): RunModel
{
    $run = RunModel::query()->create([
        'flow' => $flow,
        'flow_class' => LinearFlow::class,
        'status' => $status,
        'trigger' => RunTrigger::Api,
    ]);

    $run->owners()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'role' => 'owner',
    ]);

    return $run;
}

function testId(string $id): string
{
    return 'data-testid="'.$id.'"';
}

/**
 * @return array<int, string>
 */
function impexNavigation(?Authenticatable $user): array
{
    $request = Request::create('/atrium');
    $request->setUserResolver(fn (): ?Authenticatable => $user);

    return array_map(fn (NavItem $item): string => $item->label, app(NavigationRegistry::class)->items($request));
}

it('shows each nav item only with the ability its page needs', function (array $abilities, array $expected): void {
    grant($abilities);

    expect(array_values(array_intersect(impexNavigation($this->user), ['Runs', 'Messages', 'Flows', 'Channels'])))
        ->toBe($expected);
})->with([
    'nothing' => [[], []],
    'runs' => [['viewAny Run'], ['Runs']],
    'messages' => [['viewAny Message'], ['Messages']],
    'flows' => [['viewAny FlowOverride'], ['Flows']],
    'channels' => [['viewAny Channel'], ['Channels']],
]);

it('hides every nav item from a guest while authorization is on', function (): void {
    grant(['viewAny Run', 'viewAny Message', 'viewAny FlowOverride']);

    expect(array_intersect(impexNavigation(null), ['Runs', 'Messages', 'Flows', 'Channels']))->toBe([]);
});

it('offers widgets only with the ability they show', function (): void {
    $request = Request::create('/atrium');
    $request->setUserResolver(fn (): User => $this->user);

    $visible = fn (): array => array_values(array_map(
        fn (WidgetDefinition $widget): string => $widget->key,
        array_filter(app(ImpexPlugin::class)->widgets(), fn (WidgetDefinition $widget): bool => $widget->isAuthorized($request)),
    ));

    expect($visible())->toBe([]);

    grant(['viewAny Run', 'viewAny Message']);

    expect($visible())->toBe(['impex.run-status', 'impex.recent-failures', 'impex.message-volume']);
});

it('refuses each page without its ability', function (string $route, string $method): void {
    $run = ownedRun($this->user);

    $message = MessageModel::query()->create([
        'direction' => Direction::Inbound, 'channel' => 'webhook', 'endpoint' => '/hook',
        'transport' => 'http', 'bytes' => 1, 'occurred_at' => now(), 'run_id' => $run->getKey(),
    ]);

    $parameters = match ($route) {
        'atrium.impex.messages.show' => $message,
        'atrium.impex.flows.run' => 'linear',
        'atrium.impex.runs.index', 'atrium.impex.messages.index', 'atrium.impex.flows.index' => [],
        default => $run,
    };

    $this->actingAs($this->user)->{$method}(route($route, $parameters))->assertForbidden();
})->with([
    'runs' => ['atrium.impex.runs.index', 'get'],
    'run' => ['atrium.impex.runs.show', 'get'],
    'cancel' => ['atrium.impex.runs.cancel', 'post'],
    'retry' => ['atrium.impex.runs.retry', 'post'],
    'signal' => ['atrium.impex.runs.signal', 'post'],
    'messages' => ['atrium.impex.messages.index', 'get'],
    'message' => ['atrium.impex.messages.show', 'get'],
    'flows' => ['atrium.impex.flows.index', 'get'],
    'start a flow' => ['atrium.impex.flows.run', 'post'],
]);

it('leaves a run untouched when the action is refused', function (): void {
    grant(['view Run']);

    $run = ownedRun($this->user);

    $this->actingAs($this->user)->post(route('atrium.impex.runs.cancel', $run))->assertForbidden();
    $this->actingAs($this->user)->post(route('atrium.impex.flows.run', 'linear'))->assertForbidden();

    expect($run->fresh()->status)->toBe(RunStatus::Running)
        ->and(RunModel::query()->count())->toBe(1);
});

it('shows a run without the controls the viewer may not use', function (): void {
    grant(['view Run']);

    $run = ownedRun($this->user, RunStatus::Waiting);

    $this->actingAs($this->user)
        ->get(route('atrium.impex.runs.show', $run))
        ->assertOk()
        ->assertSee(testId('run-status'), false)
        ->assertDontSee(testId('cancel-run'), false)
        ->assertDontSee(testId('retry-run'), false)
        ->assertDontSee(testId('signal-card'), false)
        ->assertDontSee(testId('steps-card'), false)
        ->assertDontSee(testId('owners-card'), false)
        ->assertDontSee(testId('messages-card'), false);
});

it('shows each run control with its ability', function (string $ability, string $control): void {
    grant(['view Run', $ability]);

    $run = ownedRun($this->user, RunStatus::Waiting);

    $this->actingAs($this->user)
        ->get(route('atrium.impex.runs.show', $run))
        ->assertOk()
        ->assertSee(testId($control), false);
})->with([
    'cancel' => ['cancel Run', 'cancel-run'],
    'retry' => ['retry Run', 'retry-run'],
    'signal' => ['create Signal', 'signal-card'],
    'steps' => ['viewAny RunStep', 'steps-card'],
    'owners' => ['viewAny RunOwner', 'owners-card'],
    'messages' => ['viewAny Message', 'messages-card'],
]);

it('runs an action once its ability is granted', function (): void {
    grant(['view Run', 'cancel Run']);

    $run = ownedRun($this->user);

    $this->actingAs($this->user)->post(route('atrium.impex.runs.cancel', $run))->assertRedirect();

    expect($run->fresh()->status)->toBe(RunStatus::Cancelled);
});

it('offers to start a flow only with create on runs, and the starter owns the run', function (): void {
    grant(['viewAny FlowOverride']);

    $this->actingAs($this->user)
        ->get(route('atrium.impex.flows.index'))
        ->assertOk()
        ->assertSee('linear')
        ->assertDontSee(testId('run-linear'), false);

    grant(['viewAny FlowOverride', 'create Run']);

    $this->actingAs($this->user)
        ->get(route('atrium.impex.flows.index'))
        ->assertSee(testId('run-linear'), false);

    $this->actingAs($this->user)->post(route('atrium.impex.flows.run', 'linear'))->assertRedirect();

    $run = RunModel::query()->sole();

    expect($run->owners()->where('owner_id', (string) $this->user->getKey())->exists())->toBeTrue();
});

it('lists only the runs and messages the viewer owns, as the API does', function (): void {
    grant(['viewAny Run', 'viewAny Message']);

    $other = User::forceCreate(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'x']);

    $mine = ownedRun($this->user, flow: 'mine-flow');
    ownedRun($other, flow: 'theirs-flow');

    MessageModel::query()->create([
        'direction' => Direction::Inbound, 'channel' => 'theirs-channel', 'endpoint' => '/hook',
        'transport' => 'http', 'bytes' => 1, 'occurred_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('atrium.impex.runs.index'))
        ->assertOk()
        ->assertSee('mine-flow')
        ->assertDontSee('theirs-flow');

    $this->actingAs($this->user)
        ->get(route('atrium.impex.messages.index'))
        ->assertOk()
        ->assertDontSee('theirs-channel');

    // Both runs are active; the nav badge counts only the viewer's.
    $this->actingAs($this->user);

    $runs = collect(app(ImpexPlugin::class)->navigation())->firstOrFail(fn (NavItem $item): bool => $item->label === 'Runs');

    expect($runs->resolveBadge())->toBe(1)
        ->and($mine->status)->toBe(RunStatus::Running);
});

it('shows everything with authorization off', function (): void {
    config()->set('impex.authorization', false);

    $run = ownedRun($this->user, RunStatus::Waiting);

    expect(array_values(array_intersect(impexNavigation(null), ['Runs', 'Messages', 'Flows', 'Channels'])))
        ->toBe(['Runs', 'Messages', 'Flows', 'Channels']);

    $this->get(route('atrium.impex.runs.show', $run))
        ->assertOk()
        ->assertSee(testId('cancel-run'), false)
        ->assertSee(testId('retry-run'), false)
        ->assertSee(testId('signal-card'), false)
        ->assertSee(testId('steps-card'), false)
        ->assertSee(testId('owners-card'), false)
        ->assertSee(testId('messages-card'), false);

    $this->get(route('atrium.impex.flows.index'))->assertSee(testId('run-linear'), false);
});
