<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The Message `updated` Eloquent event.
 */
final class MessageUpdatedEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public MessageModel $message) {}

    public function model(): Model
    {
        return $this->message;
    }

    public function hook(): string
    {
        return 'updated';
    }
}
