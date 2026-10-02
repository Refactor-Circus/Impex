<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Atrium\Navigation\NavItem;
use JayI\Atrium\Plugins\PluginRegistry;
use JayI\Atrium\Widgets\WidgetDefinition;
use JayI\Atrium\Widgets\WidgetRegistry;
use JayI\Impex\Atrium\Badges;
use JayI\Impex\Atrium\ImpexPlugin;
use JayI\Impex\Enums\Direction;
use JayI\Impex\Enums\RunStatus;
use JayI\Impex\Enums\StepStatus;
use JayI\Impex\Features\ImpexSupportFeature;
use JayI\Impex\Tests\Fixtures\Features\OrphanedSupportFeature;

it('registers itself with atrium', function (): void {
    expect(app(PluginRegistry::class)->has('impex'))->toBeTrue();
});

it('contributes navigation for every section', function (): void {
    $labels = array_map(
        fn (NavItem $item): string => $item->label,
        app(ImpexPlugin::class)->navigation(),
    );

    expect($labels)->toBe(['Runs', 'Messages', 'Flows', 'Channels']);
});

it('registers its routes inside the atrium group', function (): void {
    expect(Route::has('atrium.impex.runs.index'))->toBeTrue()
        ->and(Route::has('atrium.impex.messages.index'))->toBeTrue()
        ->and(route('atrium.impex.runs.index'))->toContain('/atrium/impex/runs');
});

it('offers widgets without placing any', function (): void {
    $keys = array_map(
        fn (WidgetDefinition $definition): string => $definition->key,
        app(ImpexPlugin::class)->widgets(),
    );

    expect($keys)->toBe(['impex.run-status', 'impex.recent-failures', 'impex.message-volume']);

    // Offered in the registry, but nothing is placed on a dashboard.
    expect(app(WidgetRegistry::class)->all())->toHaveKeys($keys);
});

it('colours every run and step status, keeping info for pending alone', function (): void {
    $variants = ['success', 'danger', 'neutral', 'warning', 'info', 'primary'];

    foreach ([...RunStatus::cases(), ...StepStatus::cases()] as $status) {
        expect(Badges::forStatus($status))->toBeIn($variants);
    }

    // Pending - waiting to start, or waiting on a signal - and nothing else.
    $pending = array_filter(
        [...RunStatus::cases(), ...StepStatus::cases()],
        fn (RunStatus|StepStatus $status): bool => Badges::forStatus($status) === 'info',
    );

    expect(array_map(fn (RunStatus|StepStatus $status): string => $status->value, array_values($pending)))
        ->toBe(['pending', 'waiting', 'pending']);
});

it('colours runs and steps as they are in progress, done, failed or over', function (): void {
    expect(Badges::forRun(RunStatus::Running))->toBe('primary')
        ->and(Badges::forRun(RunStatus::Completed))->toBe('success')
        ->and(Badges::forRun(RunStatus::Failed))->toBe('danger')
        ->and(Badges::forRun(RunStatus::RollingBack))->toBe('warning')
        ->and(Badges::forRun(RunStatus::Cancelled))->toBe('neutral')
        ->and(Badges::forStep(StepStatus::Running))->toBe('primary')
        ->and(Badges::forStep(StepStatus::Skipped))->toBe('neutral');
});

it('colours flows and signatures without info', function (): void {
    expect(Badges::forFlow(true))->toBe('success')
        ->and(Badges::forFlow(false))->toBe('neutral')
        ->and(Badges::forSignature(true))->toBe('success')
        ->and(Badges::forSignature(false))->toBe('danger')
        ->and(Badges::forSignature(null))->toBe('neutral');
});

it('maps both message directions', function (): void {
    expect(Badges::forDirection(Direction::Inbound))->toBe('neutral')
        ->and(Badges::forDirection(Direction::Outbound))->toBe('primary');
});

it('gives every navigation item an icon', function (): void {
    foreach (app(ImpexPlugin::class)->navigation() as $item) {
        expect($item->icon)->toStartWith('<svg');
    }
});

it('switches on the bundled feature, skipping classes that are not installed', function (): void {
    expect(app(ImpexPlugin::class)->features())->toBe([ImpexSupportFeature::class]);

    config()->set('impex.atrium.features', ['impex-dashboard', 'App\\Features\\Missing', ImpexSupportFeature::class]);

    expect(app(ImpexPlugin::class)->features())->toBe(['impex-dashboard', ImpexSupportFeature::class]);
});

it('skips a feature whose parent class is not installed rather than throwing', function (): void {
    config()->set('impex.atrium.features', [OrphanedSupportFeature::class, 'impex-dashboard']);

    expect(app(ImpexPlugin::class)->features())->toBe(['impex-dashboard']);
});

it('offers a settings panel', function (): void {
    expect(app(ImpexPlugin::class)->settings()?->key)->toBe('impex');
});
