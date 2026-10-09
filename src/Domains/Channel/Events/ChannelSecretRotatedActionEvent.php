<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;

/**
 * A channel got a new signing secret. The secret itself is not carried.
 */
final class ChannelSecretRotatedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ChannelModel $channel,
    ) {}
}
