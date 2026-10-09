<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Http\Requests;

use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

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
