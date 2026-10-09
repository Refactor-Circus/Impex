<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Actions\RetryRunAction;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;

final class RetryRunMcpRequest extends RunRequest
{
    protected function authorize(): bool
    {
        return $this->allows('retry', $this->run());
    }

    protected function rules(): array
    {
        return RetryRunAction::rules() + [
            'run' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $run = app(RetryRunAction::class)->execute($this->run());

        return Response::structured((new RunResource($run))->resolve());
    }
}
