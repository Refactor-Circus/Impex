<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ModelLifecycleEvent;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;

/**
 * The Channel `created` Eloquent event.
 */
final class ChannelCreatedEvent implements ModelLifecycleEvent
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
        return 'created';
    }
}
