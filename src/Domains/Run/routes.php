<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Impex\Domains\Run\Http\Controllers\RunController;
use JayI\Impex\Domains\Run\Http\Controllers\RunOwnerController;
use JayI\Impex\Domains\Run\Http\Controllers\RunStepController;

Route::get('runs', [RunController::class, 'index'])->name('runs.index');
Route::get('runs/{run}', [RunController::class, 'show'])->name('runs.show');
Route::post('runs/{run}/cancel', [RunController::class, 'cancel'])->name('runs.cancel');
Route::post('runs/{run}/retry', [RunController::class, 'retry'])->name('runs.retry');

Route::get('runs/{run}/steps', [RunStepController::class, 'index'])->name('runs.steps.index');

Route::get('runs/{run}/owners', [RunOwnerController::class, 'index'])->name('runs.owners.index');
Route::post('runs/{run}/owners', [RunOwnerController::class, 'store'])->name('runs.owners.store');
Route::delete('runs/{run}/owners/{owner}', [RunOwnerController::class, 'destroy'])->name('runs.owners.destroy');
