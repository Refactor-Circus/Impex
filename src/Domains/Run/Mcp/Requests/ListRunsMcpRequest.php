<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Actions\ListRunsAction;
use RefactorCircus\Impex\Domains\Run\Data\ListRunsData;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;
use RefactorCircus\Keystone\Mcp\Requests\Request;

final class ListRunsMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', RunModel::class);
    }

    protected function rules(): array
    {
        return ListRunsData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $runs = app(ListRunsAction::class)->execute(ListRunsData::fromValidated($validated), $this->actor());

        return RunResource::collection($runs)->toStructuredResponse();
    }
}
