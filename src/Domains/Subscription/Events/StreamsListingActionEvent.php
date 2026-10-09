<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * The streams are about to be listed.
 */
final class StreamsListingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    //
}
