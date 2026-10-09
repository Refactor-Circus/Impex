<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use RefactorCircus\Impex\Domains\Message\Http\Controllers\MessageController;

Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
