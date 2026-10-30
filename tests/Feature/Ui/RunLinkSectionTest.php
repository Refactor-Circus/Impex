<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Tests\Fixtures\LinearFlow;
use Workbench\App\Models\User;

/*
 * The run link on another package's list screen: the workbench test host
 * keyed showroom.transfers, as Showroom's transfers page is, with rows of
 * two runs Ann may view, one of Bob's she may not, and a User row. The link
 * says "Open run" beside the host's own plain flow name (Showroom D-023),
 * and names the flow for assistive technology.
 */

beforeEach(function (): void {
    config()->set('impex.authorization', true);

    $this->ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
    $this->bob = User::forceCreate(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'x']);

    $this->nightly = sectionRun('nightly-sync', $this->ann);
    $this->marked = sectionRun('<b>Orders & "returns"</b>', $this->ann);
    $this->theirs = sectionRun('bobs-flow', $this->bob);

    config()->set('test-host.rows', [$this->nightly, $this->marked, $this->theirs, $this->bob]);
});

function sectionRun(string $flow, User $owner): RunModel
{
    $run = RunModel::query()->create([
        'flow' => $flow,
        'flow_class' => LinearFlow::class,
        'status' => RunStatus::Completed,
        'trigger' => RunTrigger::Api,
    ]);

    $run->owners()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'role' => 'owner',
    ]);

    return $run;
}

/**
 * The test host's rows for the user, each row's markup by its position.
 *
 * @return list<string>
 */
function hostRows(User $user): array
{
    // A new request: Atrium's per-request answers start over, as in an app.
    app()->forgetScopedInstances();

    $content = (string) test()->actingAs($user)->get(route('test-host', 'showroom.transfers'))->assertOk()->getContent();

    $rows = [];

    foreach (range(0, 3) as $index) {
        $start = strpos($content, 'data-testid="row-'.$index.'"');
        expect($start)->not->toBeFalse();

        $end = strpos($content, 'data-testid="row-'.($index + 1).'"', (int) $start);
        $rows[] = substr($content, (int) $start, $end === false ? null : $end - (int) $start);
    }

    return $rows;
}

function runLinkHtml(RunModel $run, string $flow): string
{
    return '<a href="'.route('atrium.impex.runs.show', $run).'" data-testid="impex-run-link" class="underline-offset-2 hover:underline" aria-label="Open run '.$flow.'">Open run</a>';
}

it('links each run the viewer may view to its run page, escaping the flow name', function (): void {
    [$nightly, $marked] = hostRows($this->ann);

    expect(substr_count($nightly, 'data-testid="impex-run-link"'))->toBe(1)
        ->and($nightly)->toContain(runLinkHtml($this->nightly, 'nightly-sync'))
        ->and(substr_count($marked, 'data-testid="impex-run-link"'))->toBe(1)
        ->and($marked)->toContain(runLinkHtml($this->marked, '&lt;b&gt;Orders &amp; &quot;returns&quot;&lt;/b&gt;'))
        ->and($marked)->not->toContain('<b>Orders');
});

it('gives no link to a run the viewer may not view or to a row that is no run', function (): void {
    [, , $theirs, $user] = hostRows($this->ann);

    expect($theirs)->not->toContain('impex-run-link')
        ->and($user)->not->toContain('impex-run-link');
});

it('links an operator to a run they do not own', function (): void {
    config()->set('impex.atrium.show_all', true);

    [, , $theirs, $user] = hostRows($this->ann);

    expect($theirs)->toContain(runLinkHtml($this->theirs, 'bobs-flow'))
        ->and($user)->not->toContain('impex-run-link');
});

it('renders no link while the feature gating the run page is turned off in config', function (): void {
    // Atrium asks features of the application; this one answers from config.
    Atrium::resolveFeaturesUsing(fn (string $feature): bool => config('test-features.'.$feature, true) !== false);
    config()->set('impex.atrium.features', ['impex-ui']);

    expect(hostRows($this->ann)[0])->toContain('impex-run-link');

    config()->set('test-features.impex-ui', false);

    foreach (hostRows($this->ann) as $row) {
        expect($row)->not->toContain('impex-run-link');
    }
});

it('renders no link while the plugin is not authorized for the request', function (): void {
    config()->set('impex.atrium.features', ['impex-ui']);
    Atrium::resolveFeaturesUsing(fn (string $feature, Request $request): bool => $request->user()?->getAuthIdentifier() === $this->bob->getKey());

    foreach (hostRows($this->ann) as $row) {
        expect($row)->not->toContain('impex-run-link');
    }

    expect(hostRows($this->bob)[2])->toContain(runLinkHtml($this->theirs, 'bobs-flow'));
});

it('renders exactly the anchor, escaping the flow name', function (): void {
    $run = sectionRun('<script>alert(\'x\')</script> & "more"', $this->ann);

    $html = trim(view('impex::ui.screens.run-link', ['record' => $run])->render());

    expect($html)->toBe(
        '<a href="'.route('atrium.impex.runs.show', $run).'" data-testid="impex-run-link" class="underline-offset-2 hover:underline"'
        .' aria-label="Open run &lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt; &amp; &quot;more&quot;">Open run</a>',
    );
});
