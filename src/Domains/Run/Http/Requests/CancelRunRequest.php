<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Run\Actions\CancelRunAction;
use JayI\Impex\Domains\Run\Resources\RunResource;

final class CancelRunRequest extends RunRequest
{
    public function authorize(): bool
    {
        return $this->allows('cancel', $this->run());
    }

    public function rules(): array
    {
        return CancelRunAction::rules();
    }

    public function persist(): JsonResponse
    {
        $run = app(CancelRunAction::class)->execute($this->run(), $this->validated());

        return (new RunResource($run))->response();
    }
}
