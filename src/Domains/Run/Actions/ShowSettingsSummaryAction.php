<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use RefactorCircus\Impex\Domains\Run\Data\SettingsSummary;
use RefactorCircus\Impex\Domains\Run\Events\SettingsSummaryShowingActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\SettingsSummaryShownActionEvent;

/**
 * The engine's retention, limits and artifact settings, read key by key from
 * config with a cast and a hard default each. Only these keys are read, so a
 * key a host adds under the same sections is never exposed. It only reads,
 * so there is no transaction.
 */
final class ShowSettingsSummaryAction
{
    public function execute(): SettingsSummary
    {
        SettingsSummaryShowingActionEvent::dispatch();

        $result = $this->perform();

        SettingsSummaryShownActionEvent::dispatch($result);

        return $result;
    }

    private function perform(): SettingsSummary
    {
        return new SettingsSummary(
            retention: [
                'completed_runs_days' => $this->integer('impex.retention.completed_runs_days', 90),
                'failed_runs_days' => $this->integer('impex.retention.failed_runs_days', 365),
                'messages_days' => $this->integer('impex.retention.messages_days', 90),
                'artifacts_days' => $this->integer('impex.retention.artifacts_days', 365),
                'events_days' => $this->integer('impex.retention.events_days', 30),
                'deliveries_days' => $this->integer('impex.retention.deliveries_days', 30),
            ],
            limits: [
                'max_step_seconds' => $this->integer('impex.limits.max_step_seconds', 840),
                'resume_margin_seconds' => $this->integer('impex.limits.resume_margin_seconds', 30),
                'lease_seconds' => $this->integer('impex.limits.lease_seconds', 900),
                'lock_seconds' => $this->integer('impex.limits.lock_seconds', 120),
                'max_resumptions' => $this->integer('impex.limits.max_resumptions', 10000),
                'fan_out_max' => $this->integer('impex.limits.fan_out_max', 100),
                'sync_seconds' => $this->integer('impex.limits.sync_seconds', 15),
            ],
            artifacts: [
                'disk' => $this->disk(),
                'path' => (string) (config('impex.artifacts.path') ?? 'impex'),
                'inline_threshold' => $this->integer('impex.artifacts.inline_threshold', 65536),
                'memo_bytes' => $this->integer('impex.artifacts.memo_bytes', 16777216),
            ],
            previewBytes: $this->integer('impex.messages.preview_bytes', 2048),
        );
    }

    private function integer(string $key, int $default): int
    {
        return (int) (config($key) ?? $default);
    }

    /**
     * The artifact disk, or null when the application default is used.
     */
    private function disk(): ?string
    {
        $disk = config('impex.artifacts.disk');

        return $disk === null || $disk === '' ? null : (string) $disk;
    }
}
