<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Run\Actions\ShowSettingsSummaryAction;
use RefactorCircus\Impex\Domains\Run\Http\Resources\SettingsSummaryResource;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Keystone\Http\Requests\Request;

final class ShowSettingsSummaryRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', RunModel::class);
    }

    public function persist(): JsonResponse
    {
        $summary = app(ShowSettingsSummaryAction::class)->execute();

        return (new SettingsSummaryResource($summary))->response();
    }
}
