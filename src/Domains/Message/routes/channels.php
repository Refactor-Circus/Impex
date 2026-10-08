<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Impex\Domains\Channel\Services\ChannelRegistry;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Http\Controllers\ReceiveMessageController;

// Channels with a custom path get their own named route. Everything else is
// served by the generic endpoint below, which resolves the channel at request
// time so adding one never depends on the route cache.
/** @var ChannelRegistry $channels */
$channels = app(ChannelRegistry::class);

// Configured channels only: stored channels are served by the generic
// endpoint, so registering routes never reads the database.
foreach ($channels->configured() as $channel) {
    if ($channel->direction !== Direction::Inbound) {
        continue;
    }

    if ($channel->path === null) {
        continue;
    }

    Route::post($channel->path, ReceiveMessageController::class)
        ->defaults('channel', $channel->name)
        ->name('channels.receive.'.$channel->name);
}

Route::post('channels/{channel}', ReceiveMessageController::class)->name('channels.receive');
