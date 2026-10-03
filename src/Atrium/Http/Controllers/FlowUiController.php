<?php

declare(strict_types=1);

namespace JayI\Impex\Atrium\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JayI\Impex\Atrium\Http\Controllers\Concerns\AuthorizesScreens;
use JayI\Impex\Atrium\ScreenAccess;
use JayI\Impex\Domains\Flow\Actions\ListFlowsAction;
use JayI\Impex\Domains\Flow\Actions\RunFlowAction;
use JayI\Impex\Domains\Flow\Models\FlowOverrideModel;
use JayI\Impex\Domains\Run\Models\RunModel;

final class FlowUiController
{
    use AuthorizesScreens;

    public function index(): View
    {
        $this->authorizeScreen('viewAny', FlowOverrideModel::class);

        /** @var view-string $view */
        $view = 'impex::ui.flows.index';

        return view($view, ['flows' => app(ListFlowsAction::class)->execute()]);
    }

    public function run(Request $request, string $flow): RedirectResponse
    {
        $this->authorizeScreen('create', RunModel::class, [$flow]);

        $validated = $request->validate([
            'arguments' => ['nullable', 'string'],
        ]);

        $arguments = [];

        if (($validated['arguments'] ?? '') !== '') {
            $decoded = json_decode((string) $validated['arguments'], true);

            if (! is_array($decoded)) {
                return back()->withErrors(['arguments' => __('impex::impex.invalid_arguments')]);
            }

            $arguments = $decoded;
        }

        // The user who starts a run owns it, as with the JSON API.
        $run = app(RunFlowAction::class)->execute($flow, ['arguments' => $arguments], owner: ScreenAccess::actor());

        return redirect()
            ->route('atrium.impex.runs.show', $run)
            ->with('status', __('impex::impex.flow_started'));
    }
}
