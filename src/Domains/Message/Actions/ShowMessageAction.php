<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Actions;

use RefactorCircus\Impex\Domains\Message\Events\MessageShowingActionEvent;
use RefactorCircus\Impex\Domains\Message\Events\MessageShownActionEvent;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;

final class ShowMessageAction
{
    public function execute(MessageModel $message): MessageModel
    {
        MessageShowingActionEvent::dispatch($message);

        $result = $this->perform($message);

        MessageShownActionEvent::dispatch($result);

        return $result;
    }

    private function perform(MessageModel $message): MessageModel
    {
        return $message;
    }
}
