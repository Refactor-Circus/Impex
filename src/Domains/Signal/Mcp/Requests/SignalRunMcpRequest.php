<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Mcp\Requests\RunRequest;
use RefactorCircus\Impex\Domains\Signal\Actions\SignalRunAction;
use RefactorCircus\Impex\Domains\Signal\Data\SignalRunData;
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
        return [
            'run' => ['required', 'string', 'max:26'],
        ] + SignalRunData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $signal = app(SignalRunAction::class)->execute($this->run(), SignalRunData::fromValidated($validated));

        if (! $signal instanceof SignalModel) {
            return Response::structured(['data' => null, 'delivered' => false, 'reason' => 'The run has finished.']);
        }

        return (new SignalResource($signal))->additional(['delivered' => true])->toStructuredResponse();
    }
}
