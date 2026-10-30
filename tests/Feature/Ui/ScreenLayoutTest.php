<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Tests\Fixtures\LinearFlow;
use RefactorCircus\Impex\Tests\Fixtures\SignalFlow;

/*
 * Where each standard slot renders on Impex's own screens, per Atrium
 * criterion 15 (criterion 9): header actions after the page's own, `before`
 * and `after` around the content, `aside` in a right-hand column only when it
 * has something to show, and `row.actions` in each row's last cell after the
 * row's own controls.
 */

beforeEach(function (): void {
    // Atrium denies access outside local until a gate is defined.
    app()->detectEnvironment(fn (): string => 'local');

    config()->set('impex.flows', ['linear' => LinearFlow::class, 'signal' => SignalFlow::class]);

    View::replaceNamespace('workbench', dirname(__DIR__, 3).'/workbench/resources/views');

    $this->run = RunModel::query()->create([
        'flow' => 'linear',
        'flow_class' => LinearFlow::class,
        'status' => RunStatus::Running,
        'trigger' => RunTrigger::Api,
    ]);
});

function screenLayoutSection(string $key): SectionDefinition
{
    return SectionDefinition::make($key)
        ->view('workbench::test-host-marker')
        ->resolve(fn (): array => ['marker' => $key])
        ->public();
}

it('places the header actions after the page\'s own, and before, content, aside and after in order', function (): void {
    Atrium::screens()
        ->section('impex.run', 'header.actions', screenLayoutSection('layout.header'))
        ->section('impex.run', 'before', screenLayoutSection('layout.before'))
        ->section('impex.run', 'aside', screenLayoutSection('layout.aside'))
        ->section('impex.run', 'after', screenLayoutSection('layout.after'));

    $this->get(route('atrium.impex.runs.show', $this->run))
        ->assertOk()
        ->assertSeeInOrder([
            'data-testid="cancel-run"',
            'data-testid="retry-run"',
            'data-testid="section-layout.header"',
            'data-testid="section-layout.before"',
            'data-testid="impex-content"',
            'data-testid="steps-card"',
            'data-testid="screen-aside"',
            'data-testid="section-layout.aside"',
            'data-testid="section-layout.after"',
        ], false);
});

it('places header actions after a list page\'s own actions', function (): void {
    Atrium::screens()->section('impex.channels', 'header.actions', screenLayoutSection('layout.header'));

    $this->get(route('atrium.impex.channels.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'data-testid="new-channel"',
            'data-testid="section-layout.header"',
            'data-testid="impex-content"',
        ], false);
});

it('renders row actions in each row\'s last cell after its own controls', function (): void {
    Atrium::screens()
        ->section('impex.flows', 'before', screenLayoutSection('layout.before'))
        ->section('impex.flows', 'row.actions', screenLayoutSection('layout.row'))
        ->section('impex.flows', 'after', screenLayoutSection('layout.after'));

    $this->get(route('atrium.impex.flows.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'data-testid="section-layout.before"',
            'data-testid="impex-content"',
            'data-testid="run-linear"',
            'data-testid="section-layout.row"',
            '</td>',
            '</tr>',
            'data-testid="run-signal"',
            'data-testid="section-layout.row"',
            '</td>',
            '</tr>',
            'data-testid="section-layout.after"',
        ], false);
});

it('renders no aside column while the aside has no visible sections', function (): void {
    $this->get(route('atrium.impex.runs.show', $this->run))
        ->assertOk()
        ->assertDontSee('data-testid="screen-aside"', false);

    Atrium::screens()->section('impex.run', 'aside', SectionDefinition::make('layout.hidden')
        ->view('workbench::test-host-marker')
        ->authorize(fn (): bool => false));

    $this->get(route('atrium.impex.runs.show', $this->run))
        ->assertOk()
        ->assertDontSee('data-testid="screen-aside"', false)
        ->assertDontSee('data-testid="section-layout.hidden"', false);
});

it('registers nothing on Impex\'s own screens by default', function (): void {
    $extensions = app(ScreenExtensions::class);
    $targets = [
        'impex.runs', 'impex.run', 'impex.messages', 'impex.message', 'impex.flows',
        'impex.channels', 'impex.channel-create', 'impex.channel', 'impex.subscriptions', 'impex.subscription',
        'impex.*', '*',
    ];

    $sections = 0;
    $tabs = 0;

    foreach ($targets as $target) {
        $tabs += count($extensions->tabs($target));

        foreach (['header.actions', 'before', 'after', 'aside', 'row.actions'] as $slot) {
            $sections += count($extensions->sections($target, $slot));
        }
    }

    expect($sections)->toBe(0)->and($tabs)->toBe(0);
});
