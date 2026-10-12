<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Actions;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Flow\Data\RunFlowData;
use RefactorCircus\Impex\Domains\Flow\Events\FlowRanActionEvent;
use RefactorCircus\Impex\Domains\Flow\Events\FlowRunningActionEvent;
use RefactorCircus\Impex\Domains\Flow\Exceptions\DisabledFlowException;
use RefactorCircus\Impex\Domains\Flow\Services\FlowRegistry;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Services\Engine;
use RefactorCircus\Impex\Impex;

final class RunFlowAction
{
    public function __construct(
        private readonly Impex $impex,
        private readonly FlowRegistry $flows,
        private readonly Engine $engine,
    ) {}

    /**
     * @param  Model|null  $owner  Attached to the new run in the `owner` role.
     */
    public function execute(string $slug, RunFlowData $data = new RunFlowData, RunTrigger $trigger = RunTrigger::Api, ?Model $owner = null): RunModel
    {
        FlowRunningActionEvent::dispatch($slug, $data, $trigger, $owner);

        $result = $this->perform($slug, $data, $trigger, $owner);

        FlowRanActionEvent::dispatch($result);

        return $result;
    }

    private function perform(string $slug, RunFlowData $data, RunTrigger $trigger, ?Model $owner): RunModel
    {
        if (! $this->flows->enabled($slug)) {
            throw DisabledFlowException::slug($slug);
        }

        $run = $this->impex->run(
            slug: $slug,
            arguments: array_values($data->arguments),
            trigger: $trigger,
            idempotencyKey: $data->idempotencyKey,
            tags: $data->tags,
            owners: $owner === null ? [] : ['owner' => $owner],
            version: $data->version,
            expiresAt: $data->expiresIn,
        );

        // A caller may ask to wait, bounded by impex.limits.sync_seconds — the
        // gateway will time out long before a flow of any size finishes.
        if ($data->wait) {
            /** @var int $budget */
            $budget = config('impex.limits.sync_seconds', 15);

            return $this->engine->driveToCompletion($run, $budget);
        }

        // Report the run as it stands at response time. Under a real queue that
        // is still pending; under the sync driver the drive already ran.
        return $run->refresh();
    }
}
