<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionStartingEvent;

/**
 * The registered flows are about to be listed.
 */
final class FlowsListingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    //
}
