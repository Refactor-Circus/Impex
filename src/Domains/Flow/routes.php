<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Impex\Domains\Flow\Http\Controllers\FlowController;

Route::get('flows', [FlowController::class, 'index'])->name('flows.index');
Route::post('flows/{flow}/runs', [FlowController::class, 'run'])->name('flows.runs.store');
