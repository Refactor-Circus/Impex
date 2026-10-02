<?php

declare(strict_types=1);

namespace JayI\Impex\Http\Ui;

use Illuminate\Contracts\View\View;
use JayI\Impex\Actions\ListChannelsAction;

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
