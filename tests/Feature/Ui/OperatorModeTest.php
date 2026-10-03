<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use JayI\Atrium\Navigation\NavItem;
use JayI\Atrium\Search\SearchResult;
use JayI\Atrium\Widgets\WidgetDefinition;
use JayI\Impex\Atrium\ImpexPlugin;
use JayI\Impex\Enums\Direction;
use JayI\Impex\Enums\RunStatus;
use JayI\Impex\Enums\RunTrigger;
use JayI\Impex\Http\Ui\ScreenAccess;
use JayI\Impex\Models\Message;
use JayI\Impex\Models\Run;
use JayI\Impex\Tests\Fixtures\LinearFlow;
use Workbench\App\Models\User;

/*
 * impex.atrium.show_all makes dashboard users operators: on the Atrium
 * screens only, they see every run and may use every Impex control. These
 * tests use the bundled ownership policies, so without it a user sees only
 * the runs they own.
 */

beforeEach(function (): void {
    ValidateCsrfToken::except(['*']);

    // Atrium denies access outside local until a gate is defined.
    app()->detectEnvironment(fn (): string => 'local');

    config()->set('impex.authorization', true);
    config()->set('impex.flows', ['linear' => LinearFlow::class]);

    $this->ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
    $this->bob = User::forceCreate(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'x']);

    // Bob's run and a scheduled run nobody owns; Ann owns neither.
    $this->theirs = operatorRun('theirs-flow', RunStatus::Running, $this->bob);
    $this->unowned = operatorRun('unowned-flow', RunStatus::Failed);

    Message::query()->create([
        'direction' => Direction::Inbound, 'channel' => 'unowned-channel', 'endpoint' => '/hook',
        'transport' => 'http', 'bytes' => 1, 'occurred_at' => now(), 'run_id' => $this->unowned->getKey(),
    ]);
});

function operatorRun(string $flow, RunStatus $status, ?User $owner = null): Run
{
    $run = Run::query()->create([
        'flow' => $flow,
        'flow_class' => LinearFlow::class,
        'status' => $status,
        'trigger' => $owner === null ? RunTrigger::Schedule : RunTrigger::Api,
        'finished_at' => $status === RunStatus::Failed ? now() : null,
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

/**
 * What the signed-in user sees across the nav badge, widgets and search.
 *
 * @return array{badge: mixed, counts: array<string, int>, failures: array<int, string>, messages: int, search: array<int, string>}
 */
function dashboardTotals(User $user): array
{
    test()->actingAs($user);

    $plugin = app(ImpexPlugin::class);
    $widgets = collect($plugin->widgets())->keyBy(fn (WidgetDefinition $widget): string => $widget->key);
    $volume = $widgets['impex.message-volume']->resolveData();

    return [
        'badge' => collect($plugin->navigation())->firstOrFail(fn (NavItem $item): bool => $item->label === 'Runs')->resolveBadge(),
        'counts' => array_filter($widgets['impex.run-status']->resolveData()['counts']),
        'failures' => $widgets['impex.recent-failures']->resolveData()['runs']->pluck('flow')->all(),
        'messages' => $volume['inbound'] + $volume['outbound'],
        'search' => array_map(fn (SearchResult $result): string => $result->title, $plugin->search()?->results('flow') ?? []),
    ];
}

it('ships with operator mode off', function (): void {
    $config = require dirname(__DIR__, 3).'/config/impex.php';

    expect($config['atrium']['show_all'])->toBeFalse();
});

it('scopes the screens to the runs a user owns while show_all is off', function (): void {
    $this->actingAs($this->ann)->get(route('atrium.impex.runs.index'))
        ->assertOk()
        ->assertDontSee('theirs-flow')
        ->assertDontSee('unowned-flow');

    $this->actingAs($this->ann)->get(route('atrium.impex.messages.index'))->assertOk()->assertDontSee('unowned-channel');
    $this->actingAs($this->ann)->get(route('atrium.impex.runs.show', $this->theirs))->assertForbidden();
    $this->actingAs($this->ann)->post(route('atrium.impex.runs.cancel', $this->theirs))->assertForbidden();
    $this->actingAs($this->ann)->post(route('atrium.impex.runs.retry', $this->unowned))->assertForbidden();

    expect(dashboardTotals($this->ann))->toBe([
        'badge' => null, 'counts' => [], 'failures' => [], 'messages' => 0, 'search' => [],
    ])->and(ScreenAccess::operator())->toBeFalse();
});

it('shows every run to everyone signed in when show_all is true', function (): void {
    config()->set('impex.atrium.show_all', true);

    $this->actingAs($this->ann)->get(route('atrium.impex.runs.index'))
        ->assertOk()
        ->assertSee('theirs-flow')
        ->assertSee('unowned-flow');

    $this->actingAs($this->ann)->get(route('atrium.impex.messages.index'))->assertOk()->assertSee('unowned-channel');

    $totals = dashboardTotals($this->ann);

    expect($totals['badge'])->toBe(1)
        ->and($totals['counts'])->toBe(['running' => 1, 'failed' => 1])
        ->and($totals['failures'])->toBe(['unowned-flow'])
        ->and($totals['messages'])->toBe(1)
        ->and($totals['search'])->toEqualCanonicalizing(['theirs-flow', 'unowned-flow']);
});

it('lets an operator use every control on another user\'s run and an unowned one', function (): void {
    config()->set('impex.atrium.show_all', true);

    foreach (['cancel-run', 'steps-card', 'owners-card', 'messages-card'] as $control) {
        $this->actingAs($this->ann)->get(route('atrium.impex.runs.show', $this->theirs))
            ->assertOk()
            ->assertSee('data-testid="'.$control.'"', false);
    }

    $this->actingAs($this->ann)->get(route('atrium.impex.runs.show', $this->unowned))
        ->assertOk()
        ->assertSee('data-testid="retry-run"', false);

    $waiting = operatorRun('waiting-flow', RunStatus::Waiting);

    $this->actingAs($this->ann)->get(route('atrium.impex.runs.show', $waiting))
        ->assertOk()
        ->assertSee('data-testid="signal-card"', false);

    $this->actingAs($this->ann)->post(route('atrium.impex.runs.signal', $waiting), ['name' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();

    $message = Message::query()->sole();

    $this->actingAs($this->ann)->get(route('atrium.impex.messages.show', $message))->assertOk();

    // Hold the drive a retry dispatches, so the retried run stays running.
    Queue::fake();

    $this->actingAs($this->ann)->post(route('atrium.impex.runs.cancel', $this->theirs))->assertRedirect();
    $this->actingAs($this->ann)->post(route('atrium.impex.runs.retry', $this->unowned))->assertRedirect();

    expect($this->theirs->fresh()->status)->toBe(RunStatus::Cancelled)
        ->and($this->unowned->fresh()->status)->toBe(RunStatus::Running);
});

it('makes operators of only the users a named gate ability allows', function (): void {
    config()->set('impex.atrium.show_all', 'impex-operator');

    Gate::define('impex-operator', fn (User $user): bool => $user->is($this->ann));

    $carl = User::forceCreate(['name' => 'Carl', 'email' => 'carl@example.test', 'password' => 'x']);

    $this->actingAs($this->ann)->get(route('atrium.impex.runs.index'))->assertSee('theirs-flow')->assertSee('unowned-flow');
    $this->actingAs($this->ann)->get(route('atrium.impex.runs.show', $this->theirs))->assertOk();

    $this->actingAs($carl)->get(route('atrium.impex.runs.index'))->assertDontSee('theirs-flow')->assertDontSee('unowned-flow');
    $this->actingAs($carl)->get(route('atrium.impex.runs.show', $this->theirs))->assertForbidden();
    $this->actingAs($carl)->post(route('atrium.impex.runs.cancel', $this->theirs))->assertForbidden();

    expect(dashboardTotals($carl)['badge'])->toBeNull()
        ->and(dashboardTotals($this->ann)['badge'])->toBe(1)
        ->and($this->theirs->fresh()->status)->toBe(RunStatus::Running);
});

it('still owns the runs an operator starts from the dashboard', function (): void {
    config()->set('impex.atrium.show_all', true);

    $this->actingAs($this->ann)->post(route('atrium.impex.flows.run', 'linear'))->assertRedirect();

    $run = Run::query()->where('flow', 'linear')->sole();

    expect($run->owners()->where('owner_id', (string) $this->ann->getKey())->exists())->toBeTrue();
});

it('leaves the JSON API to the policies while show_all is on', function (): void {
    config()->set('impex.atrium.show_all', true);

    $this->actingAs($this->ann)->getJson('/impex/runs/'.$this->theirs->id)->assertForbidden();
    $this->actingAs($this->ann)->getJson('/impex/runs/'.$this->unowned->id)->assertForbidden();
    $this->actingAs($this->ann)->postJson('/impex/runs/'.$this->theirs->id.'/cancel')->assertForbidden();
    $this->actingAs($this->ann)->getJson('/impex/runs')->assertOk()->assertJsonCount(0, 'data');

    expect($this->theirs->fresh()->status)->toBe(RunStatus::Running);
});

it('changes nothing while authorization is off', function (): void {
    config()->set('impex.authorization', false);
    config()->set('impex.atrium.show_all', false);

    expect(ScreenAccess::operator())->toBeFalse()
        ->and(ScreenAccess::viewer())->toBeNull()
        ->and(ScreenAccess::allows('cancel', $this->theirs))->toBeTrue();

    $this->get(route('atrium.impex.runs.index'))->assertOk()->assertSee('theirs-flow')->assertSee('unowned-flow');
});
