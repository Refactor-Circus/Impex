<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use RefactorCircus\Impex\Domains\Signal\Http\Controllers\RunSignalController;

Route::post('runs/{run}/signals', [RunSignalController::class, 'store'])->name('runs.signals.store');
