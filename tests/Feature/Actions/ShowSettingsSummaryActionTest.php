<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RefactorCircus\Impex\Domains\Run\Actions\ShowSettingsSummaryAction;
use RefactorCircus\Impex\Domains\Run\Events\SettingsSummaryShowingActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\SettingsSummaryShownActionEvent;

it('reads every value from config, cast to its type', function (): void {
    config()->set('impex.retention', [
        'completed_runs_days' => '10',
        'failed_runs_days' => 20,
        'messages_days' => '30',
        'artifacts_days' => 40,
        'events_days' => '50',
        'deliveries_days' => 60,
    ]);
    config()->set('impex.limits', [
        'max_step_seconds' => '100',
        'resume_margin_seconds' => 5,
        'lease_seconds' => '200',
        'lock_seconds' => 60,
        'max_resumptions' => '7',
        'fan_out_max' => 8,
        'sync_seconds' => '9',
    ]);
    config()->set('impex.artifacts', [
        'disk' => 's3',
        'path' => 'exports',
        'inline_threshold' => '1024',
        'memo_bytes' => 2048,
    ]);
    config()->set('impex.messages.preview_bytes', '512');

    $summary = app(ShowSettingsSummaryAction::class)->execute();

    expect($summary->retention)->toBe([
        'completed_runs_days' => 10,
        'failed_runs_days' => 20,
        'messages_days' => 30,
        'artifacts_days' => 40,
        'events_days' => 50,
        'deliveries_days' => 60,
    ])
        ->and($summary->limits)->toBe([
            'max_step_seconds' => 100,
            'resume_margin_seconds' => 5,
            'lease_seconds' => 200,
            'lock_seconds' => 60,
            'max_resumptions' => 7,
            'fan_out_max' => 8,
            'sync_seconds' => 9,
        ])
        ->and($summary->artifacts)->toBe([
            'disk' => 's3',
            'path' => 'exports',
            'inline_threshold' => 1024,
            'memo_bytes' => 2048,
        ])
        ->and($summary->previewBytes)->toBe(512);
});

it('falls back to a hard default for every missing key', function (): void {
    // A published config older than the package may lack whole sections.
    config()->set('impex.retention', []);
    config()->set('impex.limits', []);
    config()->set('impex.artifacts', []);
    config()->set('impex.messages', []);

    $summary = app(ShowSettingsSummaryAction::class)->execute();

    expect($summary->retention)->toBe([
        'completed_runs_days' => 90,
        'failed_runs_days' => 365,
        'messages_days' => 90,
        'artifacts_days' => 365,
        'events_days' => 30,
        'deliveries_days' => 30,
    ])
        ->and($summary->limits)->toBe([
            'max_step_seconds' => 840,
            'resume_margin_seconds' => 30,
            'lease_seconds' => 900,
            'lock_seconds' => 120,
            'max_resumptions' => 10000,
            'fan_out_max' => 100,
            'sync_seconds' => 15,
        ])
        ->and($summary->artifacts)->toBe([
            'disk' => null,
            'path' => 'impex',
            'inline_threshold' => 65536,
            'memo_bytes' => 16777216,
        ])
        ->and($summary->previewBytes)->toBe(2048);
});

it('leaves out a key a host adds under a summarised section', function (): void {
    config()->set('impex.retention.host_days', 7);
    config()->set('impex.limits.host_seconds', 8);
    config()->set('impex.artifacts.secret', 'do-not-send');

    $summary = app(ShowSettingsSummaryAction::class)->execute();

    expect(array_keys($summary->retention))->toBe(['completed_runs_days', 'failed_runs_days', 'messages_days', 'artifacts_days', 'events_days', 'deliveries_days'])
        ->and(array_keys($summary->limits))->toBe(['max_step_seconds', 'resume_margin_seconds', 'lease_seconds', 'lock_seconds', 'max_resumptions', 'fan_out_max', 'sync_seconds'])
        ->and(array_keys($summary->artifacts))->toBe(['disk', 'path', 'inline_threshold', 'memo_bytes']);
});

it('sends a null artifact disk when none is set', function (): void {
    config()->set('impex.artifacts.disk', null);

    expect(app(ShowSettingsSummaryAction::class)->execute()->artifacts['disk'])->toBeNull();

    config()->set('impex.artifacts.disk', '');

    expect(app(ShowSettingsSummaryAction::class)->execute()->artifacts['disk'])->toBeNull();
});

it('dispatches the starting and finished events', function (): void {
    Event::fake([SettingsSummaryShowingActionEvent::class, SettingsSummaryShownActionEvent::class]);

    $summary = app(ShowSettingsSummaryAction::class)->execute();

    Event::assertDispatched(SettingsSummaryShowingActionEvent::class);
    Event::assertDispatched(
        SettingsSummaryShownActionEvent::class,
        fn (SettingsSummaryShownActionEvent $event): bool => $event->summary === $summary,
    );
});
