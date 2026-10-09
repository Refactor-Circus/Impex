<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Mcp\Requests\RunRequest;
use RefactorCircus\Impex\Domains\Signal\Actions\SignalRunAction;
use RefactorCircus\Impex\Domains\Signal\Models\SignalModel;
use RefactorCircus\Impex\Domains\Signal\Resources\SignalResource;

final class SignalRunMcpRequest extends RunRequest
{
    protected function authorize(): bool
    {
        return $this->allows('create', SignalModel::class, [$this->run()]);
    }

    protected function rules(): array
    {
        return SignalRunAction::rules() + [
            'run' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $signal = app(SignalRunAction::class)->execute($this->run(), $validated);

        if (! $signal instanceof SignalModel) {
            return Response::structured(['delivered' => false, 'reason' => 'The run has finished.']);
        }

        return Response::structured((new SignalResource($signal))->resolve() + ['delivered' => true]);
    }
}
