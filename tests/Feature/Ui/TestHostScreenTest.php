<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Domains\Screens\Data\TabDefinition;
use RefactorCircus\Atrium\Facades\Atrium;
use Workbench\App\Models\User;

/*
 * The workbench test host stands in for another package's list screen (keyed
 * showroom.transfers, as Showroom's would be), so the run link is tested
 * without Showroom as a dependency. These tests prove the host itself:
 * its rows, the standard slots and the tab bar.
 */

beforeEach(function (): void {
    config()->set('test-host.rows', [
        new User()->forceFill(['id' => 1, 'name' => 'Row 1']),
        new User()->forceFill(['id' => 2, 'name' => 'Row 2']),
    ]);

    $this->actingAs(User::factory()->create());
});

function testHostSection(string $key): SectionDefinition
{
    return SectionDefinition::make($key)
        ->view('workbench::test-host-marker')
        ->resolve(fn (): array => ['marker' => $key])
        ->public();
}

it('renders its rows and a section in every standard slot of a list screen', function (): void {
    foreach (['header.actions', 'before', 'row.actions', 'after'] as $slot) {
        Atrium::screens()->section('showroom.transfers', $slot, testHostSection('test.'.$slot));
    }

    $this->get(route('test-host', 'showroom.transfers'))
        ->assertOk()
        ->assertSeeInOrder([
            'data-testid="section-test.header.actions"',
            'marker test.header.actions',
            'data-testid="section-test.before"',
            'marker test.before',
            'data-testid="host-content"',
            'data-testid="row-0"',
            'marker test.row.actions for 1',
            'data-testid="row-1"',
            'marker test.row.actions for 2',
            'data-testid="section-test.after"',
            'marker test.after',
        ], false);
});

it('renders the rows and no section markup with nothing registered', function (): void {
    $this->get(route('test-host', 'showroom.transfers'))
        ->assertOk()
        ->assertSee('data-testid="row-0"', false)
        ->assertSee('data-testid="row-1"', false)
        ->assertDontSee('data-testid="section-', false)
        ->assertDontSee('data-testid="screen-tabs"', false);
});

it('grants permissions through the gate, per row', function (): void {
    Gate::define('test.peek', fn (User $user, User $row): bool => $row->getKey() === 2);

    Atrium::screens()->section('showroom.transfers', 'row.actions', SectionDefinition::make('test.peek')
        ->view('workbench::test-host-marker')
        ->resolve(fn (): array => ['marker' => 'peek'])
        ->permission('test.peek'));

    $this->get(route('test-host', 'showroom.transfers'))
        ->assertOk()
        ->assertDontSee('marker peek for 1')
        ->assertSee('marker peek for 2');
});

it('shows a registered tab whose panel replaces the rows while header actions stay', function (): void {
    Atrium::screens()
        ->tab('showroom.transfers', TabDefinition::make('test.tab')
            ->view('workbench::test-host-marker')
            ->resolve(fn (): array => ['marker' => 'tab panel'])
            ->public())
        ->section('showroom.transfers', 'header.actions', testHostSection('test.header.actions'))
        ->section('showroom.transfers', 'before', testHostSection('test.before'));

    $this->get(route('test-host', 'showroom.transfers'))
        ->assertOk()
        ->assertSee('data-testid="tab-test.tab"', false)
        ->assertSee('data-testid="row-0"', false)
        ->assertDontSee('marker tab panel');

    $this->get(route('test-host', ['key' => 'showroom.transfers', 'tab' => 'test.tab']))
        ->assertOk()
        ->assertSee('marker tab panel')
        ->assertSee('marker test.header.actions')
        ->assertDontSee('marker test.before')
        ->assertDontSee('data-testid="row-0"', false);
});
