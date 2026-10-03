<?php

declare(strict_types=1);

namespace JayI\Impex\Atrium\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JayI\Impex\Atrium\Http\Controllers\Concerns\AuthorizesScreens;
use JayI\Impex\Atrium\ScreenAccess;
use JayI\Impex\Domains\Message\Actions\ListMessagesAction;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Run\Actions\CancelRunAction;
use JayI\Impex\Domains\Run\Actions\ListRunsAction;
use JayI\Impex\Domains\Run\Actions\ListRunStepsAction;
use JayI\Impex\Domains\Run\Actions\RetryRunAction;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\RunTrigger;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Domains\Signal\Actions\SignalRunAction;
use JayI\Impex\Domains\Signal\Models\SignalModel;

/**
 * Each action asks the same ability, of the same subject, as its JSON API
 * request, and lists are limited to the runs the user owns the same way.
 */
final class RunUiController
{
    use AuthorizesScreens;

    public function index(Request $request): View
    {
        $this->authorizeScreen('viewAny', RunModel::class);

        // Filters are validated by the Action's own rules, so the page and the
        // JSON API accept exactly the same query.
        $filters = $request->validate(ListRunsAction::rules());

        /** @var view-string $view */
        $view = 'impex::ui.runs.index';

        return view($view, [
            'runs' => app(ListRunsAction::class)->execute($filters, ScreenAccess::viewer()),
            'filters' => $filters,
            'statuses' => RunStatus::cases(),
            'triggers' => RunTrigger::cases(),
        ]);
    }

    public function show(RunModel $run): View
    {
        $this->authorizeScreen('view', $run);

        /** @var view-string $view */
        $view = 'impex::ui.runs.show';

        return view($view, [
            'run' => $run->load('owners'),
            'steps' => ScreenAccess::allows('viewAny', RunStepModel::class, [$run])
                ? app(ListRunStepsAction::class)->execute($run)
                : collect(),
            'messages' => ScreenAccess::allows('viewAny', MessageModel::class)
                ? app(ListMessagesAction::class)->execute(['run' => $run->getKey()], ScreenAccess::viewer())
                : collect(),
        ]);
    }

    public function cancel(RunModel $run): RedirectResponse
    {
        $this->authorizeScreen('cancel', $run);

        app(CancelRunAction::class)->execute($run, [
            'reason' => __('impex::impex.cancelled_from_dashboard'),
        ]);

        return redirect()
            ->route('atrium.impex.runs.show', $run)
            ->with('status', __('impex::impex.run_cancelled'));
    }

    public function retry(RunModel $run): RedirectResponse
    {
        $this->authorizeScreen('retry', $run);

        app(RetryRunAction::class)->execute($run);

        return redirect()
            ->route('atrium.impex.runs.show', $run)
            ->with('status', __('impex::impex.run_retried'));
    }

    public function signal(Request $request, RunModel $run): RedirectResponse
    {
        $this->authorizeScreen('create', SignalModel::class, [$run]);

        $data = $request->validate(SignalRunAction::rules());

        // A payload arrives from the form as a JSON string.
        if (is_string($data['payload'] ?? null)) {
            $decoded = json_decode($data['payload'], true);

            $data['payload'] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $signal = app(SignalRunAction::class)->execute($run, $data);

        return redirect()
            ->route('atrium.impex.runs.show', $run)
            ->with('status', $signal === null
                ? __('impex::impex.signal_ignored')
                : __('impex::impex.signal_sent'));
    }
}
