<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Impex\Domains\Channel\Models\ChannelModel;

/**
 * A channel was changed.
 */
final class ChannelUpdatedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ChannelModel $channel,
    ) {}
}
