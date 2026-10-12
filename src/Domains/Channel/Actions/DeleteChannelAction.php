<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Actions;

use Illuminate\Support\Facades\DB;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelDeletedActionEvent;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelDeletingActionEvent;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;

final class DeleteChannelAction
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    /**
     * The ledger keeps the channel's traffic: rows carry its name, not a key.
     */
    public function execute(ChannelModel $channel): void
    {
        ChannelDeletingActionEvent::dispatch($channel);

        $name = $channel->name;
        DB::transaction(fn (): ?bool => $channel->delete());
        $this->channels->flush();

        ChannelDeletedActionEvent::dispatch($name);
    }
}
