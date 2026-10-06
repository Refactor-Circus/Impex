<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Flow\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Impex\Domains\Flow\Actions\RunFlowAction;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Resources\RunResource;

final class StoreFlowRunRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', RunModel::class, [$this->flow()]);
    }

    public function rules(): array
    {
        return RunFlowAction::rules();
    }

    public function persist(): JsonResponse
    {
        $run = app(RunFlowAction::class)->execute($this->flow(), $this->validated(), owner: $this->actor());

        // A reused idempotency key answers with the run it first started,
        // which may belong to someone else.
        if (! $this->allows('view', $run)) {
            abort(403);
        }

        // 202, not 201: the run is queued, never executed inline, so the
        // caller is not held past the gateway's timeout.
        return (new RunResource($run))->response()->setStatusCode(202);
    }

    private function flow(): string
    {
        /** @var string $flow */
        $flow = $this->route('flow');

        return $flow;
    }
}
