<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Actions\ShowRunAction;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;

final class ShowRunRequest extends RunRequest
{
    public function authorize(): bool
    {
        return $this->allows('view', $this->run());
    }

    public function rules(): array
    {
        return ShowRunAction::rules();
    }

    public function persist(): JsonResponse
    {
        return (new RunResource(app(ShowRunAction::class)->execute($this->run())))->response();
    }
}
