<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Impex\Domains\Subscription\Http\Controllers\StreamController;
use JayI\Impex\Domains\Subscription\Http\Controllers\SubscriberController;
use JayI\Impex\Domains\Subscription\Http\Controllers\SubscriptionController;

// The operator API: managing subscribers and their subscriptions on their
// behalf. Subscribers manage their own through routes/subscriber.php.
Route::get('streams', StreamController::class)->name('streams.index');

Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
Route::post('subscribers', [SubscriberController::class, 'store'])->name('subscribers.store');
Route::get('subscribers/{subscriber}', [SubscriberController::class, 'show'])->name('subscribers.show');
Route::patch('subscribers/{subscriber}', [SubscriberController::class, 'update'])->name('subscribers.update');
Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');
Route::post('subscribers/{subscriber}/subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');

require __DIR__.'/routes/subscriptions.php';
