<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Actions\ListRunOwnersAction;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Impex\Domains\Run\Resources\RunOwnerResource;

final class IndexRunOwnersRequest extends RunRequest
{
    public function authorize(): bool
    {
        return $this->allows('viewAny', RunOwnerModel::class, [$this->run()]);
    }

    public function persist(): JsonResponse
    {
        return RunOwnerResource::collection(app(ListRunOwnersAction::class)->execute($this->run()))->response();
    }
}
