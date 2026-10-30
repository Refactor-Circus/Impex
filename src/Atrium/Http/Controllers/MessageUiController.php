<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Atrium\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Impex\Atrium\Http\Controllers\Concerns\AuthorizesScreens;
use RefactorCircus\Impex\Atrium\ScreenAccess;
use RefactorCircus\Impex\Atrium\Screens;
use RefactorCircus\Impex\Domains\Message\Actions\ListMessagesAction;
use RefactorCircus\Impex\Domains\Message\Data\ListMessagesData;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;

final class MessageUiController
{
    use AuthorizesScreens;

    public function index(Request $request, ScreenExtensions $extensions): View
    {
        $this->authorizeScreen('viewAny', MessageModel::class);

        $filters = ListMessagesData::from($request->all());

        /** @var view-string $view */
        $view = 'impex::ui.messages.index';

        return view($view, [
            'resolved' => $extensions->resolve(Screens::declare('impex.messages', Screen::LIST, null, MessageModel::class), $request),
            'messages' => app(ListMessagesAction::class)->execute($filters, ScreenAccess::viewer()),
            'filters' => $request->all(),
            'directions' => Direction::cases(),
        ]);
    }

    public function show(Request $request, ScreenExtensions $extensions, MessageModel $message): View
    {
        $this->authorizeScreen('view', $message);

        /** @var view-string $view */
        $view = 'impex::ui.messages.show';

        return view($view, [
            'resolved' => $extensions->resolve(Screens::declare('impex.message', Screen::DETAIL, $message, null), $request),
            'message' => $message,
        ]);
    }
}
