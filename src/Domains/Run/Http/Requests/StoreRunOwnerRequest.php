<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Actions\AttachRunOwnerAction;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Impex\Domains\Run\Resources\RunOwnerResource;

final class StoreRunOwnerRequest extends RunRequest
{
    public function authorize(): bool
    {
        return $this->allows('create', RunOwnerModel::class, [$this->run()]);
    }

    public function rules(): array
    {
        return AttachRunOwnerAction::rules();
    }

    public function persist(): JsonResponse
    {
        $owner = app(AttachRunOwnerAction::class)->execute($this->run(), $this->validated());

        return (new RunOwnerResource($owner))->response()->setStatusCode(201);
    }
}
