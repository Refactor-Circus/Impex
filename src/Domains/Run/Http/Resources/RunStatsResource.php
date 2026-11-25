<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Run\Data\RunStats;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * The run and message figures a dashboard shows.
 *
 * @property RunStats $resource
 */
final class RunStatsResource extends JsonResource
{
    public function __construct(RunStats $stats)
    {
        parent::__construct($stats);
    }

    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        $byStatus = [];

        foreach (RunStatus::cases() as $status) {
            $byStatus[$status->value] = $schema->integer()->required();
        }

        return [
            'runs' => $schema->object([
                'by_status' => $schema->object($byStatus)->description('Runs per status, every status present.')->required(),
                'active' => $schema->integer()->description('Runs that may still be driven or signalled.')->required(),
                'recent_failures' => $schema->array()->items($schema->object(RunResource::schema($schema)))->description('The latest failed runs, newest finish first, at most 5.')->required(),
            ])->required(),
            'messages' => $schema->object([
                'window_hours' => $schema->integer()->description('How far back the counts look.')->required(),
                'inbound' => $schema->integer()->required(),
                'outbound' => $schema->integer()->required(),
            ])->nullable()->description('Null when the caller may not list messages.')->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'runs' => [
                'by_status' => $this->resource->byStatus,
                'active' => $this->resource->active,
                'recent_failures' => RunResource::collection($this->resource->recentFailures),
            ],
            'messages' => $this->resource->messages,
        ];
    }
}
