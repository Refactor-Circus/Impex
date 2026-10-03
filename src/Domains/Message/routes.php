<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Impex\Domains\Message\Http\Controllers\ChannelIndexController;
use JayI\Impex\Domains\Message\Http\Controllers\MessageController;

Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');

// The channel listing is an operator read — what boundaries exist, not
// traffic across one — so it stays on the operator stack. Only the receive
// endpoints in routes/channels.php use the signature-authenticated stack.
Route::get('channels', ChannelIndexController::class)->name('channels.index');
