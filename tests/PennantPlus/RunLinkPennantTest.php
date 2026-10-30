<?php

declare(strict_types=1);

use Laravel\Pennant\Feature;
use RefactorCircus\Impex\Atrium\Features\ImpexSupportFeature;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Tests\Fixtures\LinearFlow;
use Workbench\App\Models\User;

/*
 * The run link follows the feature that gates Impex's run page: with
 * PennantPlus answering Atrium's feature checks, turning ImpexSupportFeature
 * off through Pennant hides the link on another package's screen.
 */

it('renders no run link once the feature gating the run page is off in pennant', function (): void {
    $run = RunModel::query()->create([
        'flow' => 'nightly-sync',
        'flow_class' => LinearFlow::class,
        'status' => RunStatus::Completed,
        'trigger' => RunTrigger::Schedule,
    ]);

    config()->set('test-host.rows', [$run]);

    $this->actingAs(User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']));

    $this->get(route('test-host', 'showroom.transfers'))
        ->assertOk()
        ->assertSee(route('atrium.impex.runs.show', $run), false)
        ->assertSee('data-testid="impex-run-link"', false);

    Feature::for(null)->deactivate(ImpexSupportFeature::class);

    // A new request: Atrium's per-request answers start over, as in an app.
    app()->forgetScopedInstances();

    $this->get(route('test-host', 'showroom.transfers'))
        ->assertOk()
        ->assertSee('data-testid="row-0"', false)
        ->assertDontSee('data-testid="impex-run-link"', false);
});
