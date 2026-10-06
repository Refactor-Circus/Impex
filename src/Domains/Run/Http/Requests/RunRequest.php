<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Http\Requests;

use JayI\Foundation\Http\Requests\Request;
use JayI\Impex\Domains\Run\Models\RunModel;

abstract class RunRequest extends Request
{
    protected function run(): RunModel
    {
        $run = $this->route('run');

        if (! $run instanceof RunModel) {
            abort(404);
        }

        return $run;
    }
}
