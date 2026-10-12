<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Run\Actions\AttachRunOwnerAction;
use RefactorCircus\Impex\Domains\Run\Data\AttachRunOwnerData;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;
use RefactorCircus\Impex\Domains\Run\Resources\RunOwnerResource;

final class AttachRunOwnerMcpRequest extends RunRequest
{
    protected function authorize(): bool
    {
        return $this->allows('create', RunOwnerModel::class, [$this->run()]);
    }

    protected function rules(): array
    {
        return [
            'run' => ['required', 'string', 'max:26'],
        ] + AttachRunOwnerData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $owner = app(AttachRunOwnerAction::class)->execute($this->run(), AttachRunOwnerData::fromValidated($validated));

        return (new RunOwnerResource($owner))->toStructuredResponse();
    }
}
