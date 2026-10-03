<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Mcp\Requests;

use JayI\Impex\Domains\Run\Actions\ListRunsAction;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Resources\RunResource;
use JayI\Impex\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class ListRunsMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', RunModel::class);
    }

    protected function rules(): array
    {
        return ListRunsAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $runs = app(ListRunsAction::class)->execute($validated, $this->actor());

        return $this->structuredCollection(
            RunResource::collection($runs)->resolve(),
            ['next_cursor' => $runs->nextCursor()?->encode()],
        );
    }
}
