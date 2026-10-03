<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Impex\Contracts\ActionFinishedEvent;

/**
 * The inbound channels were listed.
 */
final class ChannelsListedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $channels
     */
    public function __construct(
        public array $channels,
    ) {}
}
