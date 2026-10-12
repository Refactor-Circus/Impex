<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Channel\Data\CreateChannelData;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * A channel is about to be created.
 */
final class ChannelCreatingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public CreateChannelData $data,
        public ?Model $owner = null,
    ) {}
}
