<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Atrium\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Impex\Atrium\Http\Controllers\Concerns\AuthorizesScreens;
use RefactorCircus\Impex\Atrium\ScreenAccess;
use RefactorCircus\Impex\Atrium\Screens;
use RefactorCircus\Impex\Domains\Flow\Actions\ListFlowsAction;
use RefactorCircus\Impex\Domains\Flow\Actions\RunFlowAction;
use RefactorCircus\Impex\Domains\Flow\Data\RunFlowData;
use RefactorCircus\Impex\Domains\Flow\Models\FlowOverrideModel;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

final class FlowUiController
{
    use AuthorizesScreens;

    public function index(Request $request, ScreenExtensions $extensions): View
    {
        $this->authorizeScreen('viewAny', FlowOverrideModel::class);

        $flows = app(ListFlowsAction::class)->execute();

        /** @var view-string $view */
        $view = 'impex::ui.flows.index';

        return view($view, [
            'resolved' => $extensions->resolve(Screens::declare('impex.flows', Screen::LIST, null, FlowOverrideModel::class), $request),
            'flows' => $flows,
            'rows' => $this->rows($flows),
        ]);
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
        $run = app(RunFlowAction::class)->execute($flow, new RunFlowData(arguments: $arguments), owner: ScreenAccess::actor());

        return redirect()
            ->route('atrium.impex.runs.show', $run)
            ->with('status', __('impex::impex.flow_started'));
    }

    /**
     * Each listed flow's row model for its `row.actions` sections, by slug:
     * its stored override, from one query for every slug, or else an
     * unsaved stand-in naming the slug.
     *
     * @param  array<int, array<string, mixed>>  $flows
     * @return array<string, FlowOverrideModel>
     */
    private function rows(array $flows): array
    {
        $slugs = array_map(fn (array $flow): string => (string) $flow['slug'], $flows);

        if ($slugs === []) {
            return [];
        }

        $stored = FlowOverrideModel::query()->whereIn('slug', $slugs)->get()->keyBy('slug');
        $rows = [];

        foreach ($slugs as $slug) {
            $rows[$slug] = $stored->get($slug) ?? new FlowOverrideModel(['slug' => $slug]);
        }

        return $rows;
    }
}
