<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Mcp\Requests;

use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Keystone\Mcp\Requests\Request;

abstract class RunRequest extends Request
{
    protected function run(): RunModel
    {
        /** @var string $id */
        $id = $this->get('run');

        return RunModel::query()->findOrFail($id);
    }
}
