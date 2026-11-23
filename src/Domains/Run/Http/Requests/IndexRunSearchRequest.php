<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Actions\SearchRunsAction;
use RefactorCircus\Impex\Domains\Run\Data\SearchRunsData;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;
use RefactorCircus\Keystone\Http\Requests\Request;

final class IndexRunSearchRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', RunModel::class);
    }

    public function rules(): array
    {
        return SearchRunsData::rules();
    }

    public function persist(): JsonResponse
    {
        $runs = app(SearchRunsAction::class)->execute(SearchRunsData::fromValidated($this->validated()), $this->actor());

        return RunResource::collection($runs)->response();
    }

    /**
     * Trim the query, so one that is only spaces counts as missing.
     */
    protected function prepareForValidation(): void
    {
        $q = $this->input('q');

        if (is_string($q)) {
            $this->merge(['q' => trim($q)]);
        }
    }
}
