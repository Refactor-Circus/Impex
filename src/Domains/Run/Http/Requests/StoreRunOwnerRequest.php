<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Actions\AttachRunOwnerAction;
use RefactorCircus\Impex\Domains\Run\Data\AttachRunOwnerData;
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
        return AttachRunOwnerData::rules();
    }

    public function persist(): JsonResponse
    {
        $owner = app(AttachRunOwnerAction::class)->execute($this->run(), AttachRunOwnerData::fromValidated($this->validated()));

        return (new RunOwnerResource($owner))->response()->setStatusCode(201);
    }
}
