<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Impex\Atrium\Screens;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Tests\Fixtures\LinearFlow;
use Workbench\App\Models\User;

/*
 * An Impex screen grants a section's permission() as its own pages do:
 * through Impex's ScreenAccess, operators included, on the screen's record or,
 * with none, on the screen's model class (criterion 8). These tests use the
 * bundled ownership policies, with authorization on.
 */

beforeEach(function (): void {
    // Atrium denies access outside local until a gate is defined.
    app()->detectEnvironment(fn (): string => 'local');

    config()->set('impex.authorization', true);
    config()->set('impex.flows', ['linear' => LinearFlow::class]);

    View::replaceNamespace('workbench', dirname(__DIR__, 3).'/workbench/resources/views');

    $this->ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
    $this->bob = User::forceCreate(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'x']);

    $this->run = RunModel::query()->create([
        'flow' => 'linear',
        'flow_class' => LinearFlow::class,
        'status' => RunStatus::Completed,
        'trigger' => RunTrigger::Api,
    ]);

    $this->run->owners()->create([
        'owner_type' => $this->ann->getMorphClass(),
        'owner_id' => (string) $this->ann->getKey(),
        'role' => 'owner',
    ]);
});

function screenAccessSection(string $key, string $permission): SectionDefinition
{
    return SectionDefinition::make($key)
        ->view('workbench::test-host-marker')
        ->resolve(fn (): array => ['marker' => $key])
        ->permission($permission);
}

/**
 * The keys of the `before` sections the user sees on a screen declared by
 * Impex, resolved as its controller resolves it.
 *
 * @param  class-string<Model>|null  $subject
 * @return list<string>
 */
function screenAccessVisible(User $user, string $key, string $kind, ?RunModel $record, ?string $subject): array
{
    // Answers are remembered per request, so each call starts a new one.
    app()->forgetScopedInstances();

    $request = Request::create('/');
    $request->setUserResolver(fn (): User => $user);

    $resolved = app(ScreenExtensions::class)->resolve(Screens::declare($key, $kind, $record, $subject), $request);

    return array_map(fn (SectionDefinition $section): string => $section->key, $resolved->sections('before'));
}

it('shows a view section on a run to its owner and hides it from another user', function (): void {
    Atrium::screens()->section('impex.run', 'before', screenAccessSection('test.view', 'view'));

    $this->actingAs($this->ann)->get(route('atrium.impex.runs.show', $this->run))
        ->assertOk()
        ->assertSee('marker test.view');

    expect(screenAccessVisible($this->ann, 'impex.run', Screen::DETAIL, $this->run, null))->toBe(['test.view'])
        ->and(screenAccessVisible($this->bob, 'impex.run', Screen::DETAIL, $this->run, null))->toBe([]);
});

it('shows a view section on a run to an operator who does not own it', function (): void {
    config()->set('impex.atrium.show_all', true);

    Atrium::screens()->section('impex.run', 'before', screenAccessSection('test.view', 'view'));

    $this->actingAs($this->bob)->get(route('atrium.impex.runs.show', $this->run))
        ->assertOk()
        ->assertSee('marker test.view');

    expect(screenAccessVisible($this->bob, 'impex.run', Screen::DETAIL, $this->run, null))->toBe(['test.view']);
});

it('asks a viewAny section on the runs list about the run class', function (): void {
    $allowed = true;
    $asked = [];

    // Answers only for the run class, so every other check is untouched.
    Gate::before(function (mixed $user, string $ability, array $arguments) use (&$allowed, &$asked): ?bool {
        if ($ability !== 'viewAny' || ($arguments[0] ?? null) !== RunModel::class) {
            return null;
        }

        $asked[] = $arguments[0];

        return $allowed;
    });

    Atrium::screens()->section('impex.runs', 'before', screenAccessSection('test.view-any', 'viewAny'));

    $this->actingAs($this->ann)->get(route('atrium.impex.runs.index'))
        ->assertOk()
        ->assertSee('marker test.view-any');

    expect(screenAccessVisible($this->ann, 'impex.runs', Screen::LIST, null, RunModel::class))->toBe(['test.view-any'])
        ->and($asked)->toContain(RunModel::class);

    $allowed = false;

    expect(screenAccessVisible($this->ann, 'impex.runs', Screen::LIST, null, RunModel::class))->toBe([]);
});
