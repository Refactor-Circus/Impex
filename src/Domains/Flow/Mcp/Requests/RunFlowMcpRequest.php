<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Flow\Actions\RunFlowAction;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;
use RefactorCircus\Keystone\Mcp\Requests\Request;

final class RunFlowMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', RunModel::class, [$this->flow()]);
    }

    protected function rules(): array
    {
        return RunFlowAction::rules() + [
            'flow' => ['required', 'string', 'max:191'],
        ];
    }

    protected function handle(array $validated): Response|ResponseFactory
    {
        $run = app(RunFlowAction::class)->execute($this->flow(), $validated, RunTrigger::Mcp, $this->actor());

        // A reused idempotency key answers with the run it first started,
        // which may belong to someone else.
        if (! $this->allows('view', $run)) {
            return Response::error('Unauthorized.');
        }

        return Response::structured((new RunResource($run))->resolve());
    }

    private function flow(): string
    {
        $flow = $this->get('flow');

        return is_string($flow) ? $flow : '';
    }
}
