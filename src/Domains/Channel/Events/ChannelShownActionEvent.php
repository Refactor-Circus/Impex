<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;

/**
 * A channel was shown.
 */
final class ChannelShownActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ChannelConfig $channel,
    ) {}
}
