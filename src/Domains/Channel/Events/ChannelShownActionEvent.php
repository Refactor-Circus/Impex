<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Impex\Domains\Channel\Data\ChannelConfig;

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
