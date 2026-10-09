<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use Laravel\Mcp\Response;
use RefactorCircus\Impex\Domains\Run\Actions\DetachRunOwnerAction;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;

final class DetachRunOwnerMcpRequest extends RunRequest
{
    protected function authorize(): bool
    {
        return $this->allows('delete', $this->owner());
    }

    protected function rules(): array
    {
        return DetachRunOwnerAction::rules() + [
            'run' => ['required', 'string', 'max:26'],
            'owner' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): Response
    {
        app(DetachRunOwnerAction::class)->execute($this->run(), $this->owner());

        return Response::text('Owner detached.');
    }

    private function owner(): RunOwnerModel
    {
        /** @var string $id */
        $id = $this->get('owner');

        return RunOwnerModel::query()->findOrFail($id);
    }
}
