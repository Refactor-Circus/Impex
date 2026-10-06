<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Message\Models\MessageModel;

/**
 * The Message `saved` Eloquent event.
 */
final class MessageSavedEvent implements ModelLifecycleEvent
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
        return 'saved';
    }
}
