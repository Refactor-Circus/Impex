<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Flow\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Flow\Actions\ListFlowsAction;
use JayI\Impex\Domains\Flow\Models\FlowOverrideModel;
use JayI\Impex\Http\Request;

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
