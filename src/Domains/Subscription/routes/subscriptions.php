<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Impex\Domains\Subscription\Http\Controllers\SubscriptionController;

// Shared by the operator and subscriber APIs: the same paths under each
// surface's prefix and middleware.
Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
Route::patch('subscriptions/{subscription}', [SubscriptionController::class, 'update'])->name('subscriptions.update');
Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');
Route::post('subscriptions/{subscription}/ping', [SubscriptionController::class, 'ping'])->name('subscriptions.ping');
Route::post('subscriptions/{subscription}/export', [SubscriptionController::class, 'export'])->name('subscriptions.export');
Route::post('subscriptions/{subscription}/rotate-secret', [SubscriptionController::class, 'rotateSecret'])->name('subscriptions.rotate-secret');
Route::post('subscriptions/{subscription}/subjects', [SubscriptionController::class, 'subjects'])->name('subscriptions.subjects');
Route::get('subscriptions/{subscription}/events', [SubscriptionController::class, 'events'])->name('subscriptions.events');
Route::get('subscriptions/{subscription}/deliveries', [SubscriptionController::class, 'deliveries'])->name('subscriptions.deliveries');
