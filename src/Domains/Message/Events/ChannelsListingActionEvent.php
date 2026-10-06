<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionStartingEvent;

/**
 * The inbound channels are about to be listed.
 */
final class ChannelsListingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    //
}
