<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionStartingEvent;

/**
 * The inbound channels are about to be listed.
 */
final class ChannelsListingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    //
}
