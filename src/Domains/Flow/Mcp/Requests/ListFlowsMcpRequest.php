<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Flow\Actions\ListFlowsAction;
use RefactorCircus\Impex\Domains\Flow\Models\FlowOverrideModel;
use RefactorCircus\Keystone\Mcp\Requests\Request;

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
