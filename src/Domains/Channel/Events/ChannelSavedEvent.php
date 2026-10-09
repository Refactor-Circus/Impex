<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The Channel `saved` Eloquent event.
 */
final class ChannelSavedEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ChannelModel $channel) {}

    public function model(): Model
    {
        return $this->channel;
    }

    public function hook(): string
    {
        return 'saved';
    }
}
