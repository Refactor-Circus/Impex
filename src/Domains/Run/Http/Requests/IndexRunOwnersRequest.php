<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Run\Actions\ListRunOwnersAction;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;
use JayI\Impex\Domains\Run\Resources\RunOwnerResource;

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
