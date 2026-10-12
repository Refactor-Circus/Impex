<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Actions\CancelRunAction;
use RefactorCircus\Impex\Domains\Run\Data\CancelRunData;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;

final class CancelRunRequest extends RunRequest
{
    public function authorize(): bool
    {
        return $this->allows('cancel', $this->run());
    }

    public function rules(): array
    {
        return CancelRunData::rules();
    }

    public function persist(): JsonResponse
    {
        $run = app(CancelRunAction::class)->execute($this->run(), CancelRunData::fromValidated($this->validated()));

        return (new RunResource($run))->response();
    }
}
