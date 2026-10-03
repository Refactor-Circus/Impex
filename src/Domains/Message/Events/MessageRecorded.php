<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Events;

final readonly class MessageRecorded
{
    public function __construct(public string $messageId) {}
}
