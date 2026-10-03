<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Mcp\Requests;

use JayI\Impex\Domains\Run\Actions\DetachRunOwnerAction;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;
use Laravel\Mcp\Response;

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
