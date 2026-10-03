<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Mcp\Requests;

use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Mcp\Request;

abstract class RunRequest extends Request
{
    protected function run(): RunModel
    {
        /** @var string $id */
        $id = $this->get('run');

        return RunModel::query()->findOrFail($id);
    }
}
