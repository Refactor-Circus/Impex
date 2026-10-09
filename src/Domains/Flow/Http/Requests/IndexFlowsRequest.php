<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Impex\Domains\Flow\Actions\ListFlowsAction;
use RefactorCircus\Impex\Domains\Flow\Models\FlowOverrideModel;

final class IndexFlowsRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', FlowOverrideModel::class);
    }

    public function rules(): array
    {
        return ListFlowsAction::rules();
    }

    public function persist(): JsonResponse
    {
        return new JsonResponse(['data' => app(ListFlowsAction::class)->execute()]);
    }
}
