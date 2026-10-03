<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Impex\Domains\Message\Http\Controllers\ChannelController;
use JayI\Impex\Domains\Message\Services\ChannelRegistry;

// Channels with a custom path get their own named route. Everything else is
// served by the generic endpoint below, which resolves the channel at request
// time so adding one never depends on the route cache.
/** @var ChannelRegistry $channels */
$channels = app(ChannelRegistry::class);

foreach ($channels->inbound() as $channel) {
    if ($channel->path === null) {
        continue;
    }

    Route::post($channel->path, ChannelController::class)
        ->defaults('channel', $channel->name)
        ->name('channels.'.$channel->name);
}

Route::post('channels/{channel}', ChannelController::class)->name('channels.receive');
