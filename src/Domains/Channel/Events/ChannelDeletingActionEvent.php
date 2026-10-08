<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionStartingEvent;
use JayI\Impex\Domains\Channel\Models\ChannelModel;

/**
 * A channel is about to be deleted.
 */
final class ChannelDeletingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ChannelModel $channel,
    ) {}
}
