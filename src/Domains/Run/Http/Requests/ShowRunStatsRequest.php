<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Run\Actions\ShowRunStatsAction;
use RefactorCircus\Impex\Domains\Run\Http\Resources\RunStatsResource;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Keystone\Http\Requests\Request;

final class ShowRunStatsRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', RunModel::class);
    }

    public function persist(): JsonResponse
    {
        $stats = app(ShowRunStatsAction::class)->execute(
            viewer: $this->actor(),
            withMessages: $this->allows('viewAny', MessageModel::class),
        );

        return (new RunStatsResource($stats))->response();
    }
}
