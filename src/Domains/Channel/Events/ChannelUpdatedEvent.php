<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Channel\Models\ChannelModel;

/**
 * The Channel `updated` Eloquent event.
 */
final class ChannelUpdatedEvent implements ModelLifecycleEvent
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
        return 'updated';
    }
}
