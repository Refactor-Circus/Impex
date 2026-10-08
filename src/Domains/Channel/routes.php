<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Impex\Domains\Channel\Http\Controllers\ChannelController;

// Operator reads and runtime channel management. Receiving on a channel is a
// separate, signature-authenticated route group in the Message domain.
Route::get('channels', [ChannelController::class, 'index'])->name('channels.index');
Route::post('channels', [ChannelController::class, 'store'])->name('channels.store');
Route::get('channels/{name}', [ChannelController::class, 'show'])->name('channels.show');
Route::patch('channels/{channel:name}', [ChannelController::class, 'update'])->name('channels.update');
Route::delete('channels/{channel:name}', [ChannelController::class, 'destroy'])->name('channels.destroy');
Route::post('channels/{channel:name}/rotate-secret', [ChannelController::class, 'rotateSecret'])->name('channels.rotate-secret');
