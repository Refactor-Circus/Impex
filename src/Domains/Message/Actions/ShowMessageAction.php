<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Actions;

use JayI\Impex\Domains\Message\Events\MessageShowingActionEvent;
use JayI\Impex\Domains\Message\Events\MessageShownActionEvent;
use JayI\Impex\Domains\Message\Models\MessageModel;

final class ShowMessageAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

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
