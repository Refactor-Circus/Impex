<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Flow\Mcp\Requests;

use JayI\Foundation\Mcp\Requests\Request;
use JayI\Impex\Domains\Flow\Actions\RunFlowAction;
use JayI\Impex\Domains\Run\Enums\RunTrigger;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Resources\RunResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

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
