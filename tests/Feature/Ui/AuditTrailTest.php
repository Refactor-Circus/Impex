<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use JayI\Foundation\Audit\Contracts\AuditTrail;
use JayI\Foundation\Audit\Data\AuditEntry;
use JayI\Foundation\Audit\Data\AuditFilter;
use JayI\Foundation\Audit\Data\AuditPage;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\RunTrigger;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Tests\Fixtures\LinearFlow;

beforeEach(function (): void {
    // Atrium denies access outside local until a gate is defined.
    app()->detectEnvironment(fn (): string => 'local');

    config()->set('impex.authorization', false);
    config()->set('impex.flows', ['linear' => LinearFlow::class]);

    $this->run = RunModel::query()->create([
        'flow' => 'linear',
        'flow_class' => LinearFlow::class,
        'status' => RunStatus::Completed,
        'trigger' => RunTrigger::Api,
    ]);
});

function fakeImpexAuditTrail(): object
{
    $trail = new class implements AuditTrail
    {
        /** @var list<AuditFilter> */
        public array $filters = [];

        public function available(): bool
        {
            return true;
        }

        public function entries(AuditFilter $filter): AuditPage
        {
            $this->filters[] = $filter;

            return new AuditPage([new AuditEntry(
                id: 1,
                source: 'impex',
                action: 'run.cancelled',
                surface: 'atrium',
                createdAt: CarbonImmutable::now()->subMinute(),
                actorLabel: 'Ada Lovelace',
            )]);
        }
    };

    app()->instance(AuditTrail::class, $trail);

    return $trail;
}

it('renders no history on a show screen while no audit log is installed', function (): void {
    $this->get(route('atrium.impex.runs.show', $this->run))
        ->assertOk()
        ->assertDontSee('data-testid="audit-trail"', false);
});

it('shows a run its own history from the installed audit log', function (): void {
    $trail = fakeImpexAuditTrail();

    $this->get(route('atrium.impex.runs.show', $this->run))
        ->assertOk()
        ->assertSee('data-testid="audit-trail"', false)
        ->assertSee('run.cancelled')
        ->assertSee('Ada Lovelace');

    expect($trail->filters[0]->source)->toBe('impex')
        ->and($trail->filters[0]->subjectType)->toBe($this->run->getMorphClass())
        ->and($trail->filters[0]->subjectId)->toBe((string) $this->run->getKey());
});

it('shows the whole of impex history on the runs screen', function (): void {
    $trail = fakeImpexAuditTrail();

    $this->get(route('atrium.impex.runs.index'))
        ->assertOk()
        ->assertSee('data-testid="audit-trail"', false)
        ->assertSee('run.cancelled');

    expect($trail->filters[0]->source)->toBe('impex')
        ->and($trail->filters[0]->subjectId)->toBeNull();
});
