<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Run\Data\SettingsSummary;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * The engine's retention, limits and artifact settings a dashboard shows.
 *
 * @property SettingsSummary $resource
 */
final class SettingsSummaryResource extends JsonResource
{
    public function __construct(SettingsSummary $summary)
    {
        parent::__construct($summary);
    }

    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'retention' => $schema->object([
                'completed_runs_days' => $schema->integer()->required(),
                'failed_runs_days' => $schema->integer()->required(),
                'messages_days' => $schema->integer()->required(),
                'artifacts_days' => $schema->integer()->required(),
                'events_days' => $schema->integer()->required(),
                'deliveries_days' => $schema->integer()->required(),
            ])->description('Days each kind of record is kept.')->required(),
            'limits' => $schema->object([
                'max_step_seconds' => $schema->integer()->required(),
                'resume_margin_seconds' => $schema->integer()->required(),
                'lease_seconds' => $schema->integer()->required(),
                'lock_seconds' => $schema->integer()->required(),
                'max_resumptions' => $schema->integer()->required(),
                'fan_out_max' => $schema->integer()->required(),
                'sync_seconds' => $schema->integer()->required(),
            ])->description('The engine\'s limits.')->required(),
            'artifacts' => $schema->object([
                'disk' => $schema->string()->nullable()->description('Null when the application\'s default disk is used.')->required(),
                'path' => $schema->string()->required(),
                'inline_threshold' => $schema->integer()->description('Bytes above which a payload goes to the artifact disk.')->required(),
                'memo_bytes' => $schema->integer()->description('Bytes of decoded artifact values each process keeps.')->required(),
            ])->required(),
            'preview_bytes' => $schema->integer()->description('How much of a message body is kept inline.')->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'retention' => $this->resource->retention,
            'limits' => $this->resource->limits,
            'artifacts' => $this->resource->artifacts,
            'preview_bytes' => $this->resource->previewBytes,
        ];
    }
}
