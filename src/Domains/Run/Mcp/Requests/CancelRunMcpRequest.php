<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Mcp\Requests;

use JayI\Impex\Domains\Run\Actions\CancelRunAction;
use JayI\Impex\Domains\Run\Resources\RunResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class CancelRunMcpRequest extends RunRequest
{
    protected function authorize(): bool
    {
        return $this->allows('cancel', $this->run());
    }

    protected function rules(): array
    {
        return CancelRunAction::rules() + [
            'run' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $run = app(CancelRunAction::class)->execute($this->run(), $validated);

        return Response::structured((new RunResource($run))->resolve());
    }
}
