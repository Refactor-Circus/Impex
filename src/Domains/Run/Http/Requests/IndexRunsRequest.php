<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Actions\ListRunsAction;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;
use RefactorCircus\Keystone\Http\Requests\Request;

final class IndexRunsRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', RunModel::class);
    }

    public function rules(): array
    {
        return ListRunsAction::rules();
    }

    public function persist(): JsonResponse
    {
        $runs = app(ListRunsAction::class)->execute($this->validated(), $this->actor());

        return RunResource::collection($runs)->response();
    }
}
