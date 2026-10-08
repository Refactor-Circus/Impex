<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Actions;

use JayI\Impex\Domains\Channel\Events\ChannelDeletedActionEvent;
use JayI\Impex\Domains\Channel\Events\ChannelDeletingActionEvent;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Channel\Services\ChannelRegistry;

final class DeleteChannelAction
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * The ledger keeps the channel's traffic: rows carry its name, not a key.
     */
    public function execute(ChannelModel $channel): void
    {
        ChannelDeletingActionEvent::dispatch($channel);

        $name = $channel->name;
        $channel->delete();
        $this->channels->flush();

        ChannelDeletedActionEvent::dispatch($name);
    }
}
