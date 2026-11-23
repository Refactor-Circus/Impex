<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Data;

/**
 * The engine's retention, limits and artifact settings a dashboard shows:
 * a fixed whitelist, so a key a host adds to config is never exposed.
 */
final readonly class SettingsSummary
{
    /**
     * @param  array{completed_runs_days: int, failed_runs_days: int, messages_days: int, artifacts_days: int, events_days: int, deliveries_days: int}  $retention  Days each kind of record is kept.
     * @param  array{max_step_seconds: int, resume_margin_seconds: int, lease_seconds: int, lock_seconds: int, max_resumptions: int, fan_out_max: int, sync_seconds: int}  $limits  The engine's limits.
     * @param  array{disk: string|null, path: string, inline_threshold: int, memo_bytes: int}  $artifacts  Where large payloads go and when.
     * @param  int  $previewBytes  How much of a message body is kept inline.
     */
    public function __construct(
        public array $retention,
        public array $limits,
        public array $artifacts,
        public int $previewBytes,
    ) {}
}
