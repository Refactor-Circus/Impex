<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Signal\Mcp\Requests;

use JayI\Impex\Domains\Run\Mcp\Requests\RunRequest;
use JayI\Impex\Domains\Signal\Actions\SignalRunAction;
use JayI\Impex\Domains\Signal\Models\SignalModel;
use JayI\Impex\Domains\Signal\Resources\SignalResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

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
