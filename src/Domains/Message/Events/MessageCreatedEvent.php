<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ModelLifecycleEvent;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;

/**
 * The Message `created` Eloquent event.
 */
final class MessageCreatedEvent implements ModelLifecycleEvent
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
        return 'created';
    }
}
