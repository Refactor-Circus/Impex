<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Actions\CancelRunAction;
use RefactorCircus\Impex\Domains\Run\Data\CancelRunData;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;

final class CancelRunMcpRequest extends RunRequest
{
    protected function authorize(): bool
    {
        return $this->allows('cancel', $this->run());
    }

    protected function rules(): array
    {
        return [
            'run' => ['required', 'string', 'max:26'],
        ] + CancelRunData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $run = app(CancelRunAction::class)->execute($this->run(), CancelRunData::fromValidated($validated));

        return (new RunResource($run))->toStructuredResponse();
    }
}
