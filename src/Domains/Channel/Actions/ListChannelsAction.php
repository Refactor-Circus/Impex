<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Actions;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;
use RefactorCircus\Impex\Domains\Channel\Data\ListChannelsData;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelsListedActionEvent;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelsListingActionEvent;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;

final class ListChannelsAction
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    /**
     * @param  Model|null  $viewer  When given, the application's channels and
     *                              the viewer's own, never another owner's.
     * @return array<int, array<string, mixed>>
     */
    public function execute(ListChannelsData $filters = new ListChannelsData, ?Model $viewer = null): array
    {
        ChannelsListingActionEvent::dispatch();

        $result = $this->perform($filters, $viewer);

        ChannelsListedActionEvent::dispatch($result);

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function perform(ListChannelsData $filters, ?Model $viewer): array
    {
        $direction = $filters->direction;

        $visible = array_filter($this->channels->all(), function (ChannelConfig $channel) use ($direction, $viewer): bool {
            if ($direction !== null && $channel->direction !== $direction) {
                return false;
            }

            return $viewer === null
                || ! $channel->isOwned()
                || ($channel->ownerType === $viewer->getMorphClass() && $channel->ownerId === (string) $viewer->getKey());
        });

        // Never the secrets themselves: describe() leaves them out.
        return array_values(array_map(fn (ChannelConfig $channel): array => $channel->describe(), $visible));
    }
}
