<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Actions\ShowRunAction;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;

final class ShowRunMcpRequest extends RunRequest
{
    protected function authorize(): bool
    {
        return $this->allows('view', $this->run());
    }

    protected function rules(): array
    {
        return ShowRunAction::rules() + [
            'run' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $run = app(ShowRunAction::class)->execute($this->run());

        return Response::structured((new RunResource($run))->resolve());
    }
}
