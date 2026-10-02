<?php

declare(strict_types=1);

namespace JayI\Impex\Http\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use JayI\Impex\Actions\ListMessagesAction;
use JayI\Impex\Enums\Direction;
use JayI\Impex\Http\Ui\Concerns\AuthorizesScreens;
use JayI\Impex\Models\Message;

final class MessageUiController
{
    use AuthorizesScreens;

    public function index(Request $request): View
    {
        $this->authorizeScreen('viewAny', Message::class);

        $filters = $request->validate(ListMessagesAction::rules());

        /** @var view-string $view */
        $view = 'impex::ui.messages.index';

        return view($view, [
            'messages' => app(ListMessagesAction::class)->execute($filters, ScreenAccess::actor()),
            'filters' => $filters,
            'directions' => Direction::cases(),
        ]);
    }

    public function show(Message $message): View
    {
        $this->authorizeScreen('view', $message);

        /** @var view-string $view */
        $view = 'impex::ui.messages.show';

        return view($view, ['message' => $message]);
    }
}
