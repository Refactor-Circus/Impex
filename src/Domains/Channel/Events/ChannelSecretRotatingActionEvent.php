<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionStartingEvent;
use JayI\Impex\Domains\Channel\Models\ChannelModel;

/**
 * A channel is about to get a new signing secret.
 */
final class ChannelSecretRotatingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ChannelModel $channel,
    ) {}
}
