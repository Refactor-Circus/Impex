<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Signal\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Run\Http\Requests\RunRequest;
use JayI\Impex\Domains\Signal\Actions\SignalRunAction;
use JayI\Impex\Domains\Signal\Models\SignalModel;
use JayI\Impex\Domains\Signal\Resources\SignalResource;

final class StoreRunSignalRequest extends RunRequest
{
    public function authorize(): bool
    {
        return $this->allows('create', SignalModel::class, [$this->run()]);
    }

    public function rules(): array
    {
        return SignalRunAction::rules();
    }

    public function persist(): JsonResponse
    {
        $signal = app(SignalRunAction::class)->execute($this->run(), $this->validated());

        // `if_running` on a finished run is a deliberate no-op, so it answers
        // 200 with nothing delivered rather than 202.
        if (! $signal instanceof SignalModel) {
            return new JsonResponse(['data' => null, 'message' => 'Run has finished; nothing delivered.']);
        }

        return (new SignalResource($signal))->response()->setStatusCode(202);
    }
}
