<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Impex\Domains\Subscription\Http\Controllers\StreamController;
use JayI\Impex\Domains\Subscription\Http\Controllers\SubscriptionController;

// The subscriber API, under `{prefix}/subscriber`: a subscriber, signed in
// as its OAuth client, manages its own subscriptions and reads its feed.
Route::get('streams', StreamController::class)->name('streams.index');
Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');

require __DIR__.'/subscriptions.php';
