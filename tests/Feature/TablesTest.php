<?php

declare(strict_types=1);

use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Support\Tables;

it('ships the default table names in its config', function (): void {
    $config = require dirname(__DIR__, 2).'/config/impex.php';

    expect($config['tables'])->toBe(Tables::DEFAULTS);
});

it('keeps the default for tables a published config leaves out', function (): void {
    config()->set('impex.tables', ['runs' => 'crm_runs']);

    expect(Tables::name('runs'))->toBe('crm_runs')
        ->and(Tables::name('run_steps'))->toBe('impex_run_steps')
        ->and((new RunModel)->getTable())->toBe('crm_runs');
});
