<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use RefactorCircus\Foundation\Mcp\Requests\Request;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

abstract class RunRequest extends Request
{
    protected function run(): RunModel
    {
        /** @var string $id */
        $id = $this->get('run');

        return RunModel::query()->findOrFail($id);
    }
}
