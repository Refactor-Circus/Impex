<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Actions;

use JayI\Impex\Domains\Message\Data\ChannelConfig;
use JayI\Impex\Domains\Message\Events\ChannelsListedActionEvent;
use JayI\Impex\Domains\Message\Events\ChannelsListingActionEvent;
use JayI\Impex\Domains\Message\Services\ChannelRegistry;

final class ListChannelsAction
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
     * @return array<int, array<string, mixed>>
     */
    public function execute(): array
    {
        ChannelsListingActionEvent::dispatch();

        $result = $this->perform();

        ChannelsListedActionEvent::dispatch($result);

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function perform(): array
    {
        return array_values(array_map(fn (ChannelConfig $channel): array => [
            'name' => $channel->name,
            'direction' => $channel->direction->value,
            // Never the secret itself — this endpoint is readable by anyone who
            // can reach the dashboard.
            'verifies_signatures' => $channel->verifiesSignatures(),
            'flow' => $channel->flow,
            'path' => $channel->path,
        ], $this->channels->inbound()));
    }
}
