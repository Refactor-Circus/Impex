<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Flow\Mcp\Requests;

use JayI\Foundation\Mcp\Requests\Request;
use JayI\Impex\Domains\Flow\Actions\ListFlowsAction;
use JayI\Impex\Domains\Flow\Models\FlowOverrideModel;
use Laravel\Mcp\ResponseFactory;

final class ListFlowsMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', FlowOverrideModel::class);
    }

    protected function rules(): array
    {
        return ListFlowsAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        return $this->structuredCollection(app(ListFlowsAction::class)->execute());
    }
}
