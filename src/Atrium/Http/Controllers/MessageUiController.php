<?php

declare(strict_types=1);

namespace JayI\Impex\Atrium\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use JayI\Impex\Atrium\Http\Controllers\Concerns\AuthorizesScreens;
use JayI\Impex\Atrium\ScreenAccess;
use JayI\Impex\Domains\Message\Actions\ListMessagesAction;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Models\MessageModel;

final class MessageUiController
{
    use AuthorizesScreens;

    public function index(Request $request): View
    {
        $this->authorizeScreen('viewAny', MessageModel::class);

        $filters = $request->validate(ListMessagesAction::rules());

        /** @var view-string $view */
        $view = 'impex::ui.messages.index';

        return view($view, [
            'messages' => app(ListMessagesAction::class)->execute($filters, ScreenAccess::viewer()),
            'filters' => $filters,
            'directions' => Direction::cases(),
        ]);
    }

    public function show(MessageModel $message): View
    {
        $this->authorizeScreen('view', $message);

        /** @var view-string $view */
        $view = 'impex::ui.messages.show';

        return view($view, ['message' => $message]);
    }
}
