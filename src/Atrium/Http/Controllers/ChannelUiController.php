<?php

declare(strict_types=1);

namespace JayI\Impex\Atrium\Http\Controllers;

use Illuminate\Contracts\View\View;
use JayI\Impex\Atrium\ScreenAccess;
use JayI\Impex\Domains\Channel\Actions\ListChannelsAction;

final class ChannelUiController
{
    public function __invoke(): View
    {
        // No policy covers channels: the API lets anyone signed in list them.
        abort_unless(ScreenAccess::signedIn(), 403);

        /** @var view-string $view */
        $view = 'impex::ui.channels.index';

        return view($view, ['channels' => app(ListChannelsAction::class)->execute()]);
    }
}
