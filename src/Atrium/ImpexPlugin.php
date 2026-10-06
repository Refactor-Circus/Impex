<?php

declare(strict_types=1);

namespace JayI\Impex\Atrium;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use JayI\Atrium\Domains\Navigation\Data\NavItem;
use JayI\Atrium\Domains\Plugins\Support\Plugin;
use JayI\Atrium\Domains\Search\Data\SearchResult;
use JayI\Atrium\Domains\Search\Data\SearchSource;
use JayI\Atrium\Domains\Settings\Data\SettingsPanel;
use JayI\Atrium\Domains\Widgets\Data\WidgetDefinition;
use JayI\Atrium\Support\Icons;
use JayI\Foundation\Auth\Authorizer;
use JayI\Foundation\Packages\PackageRegistry;
use JayI\Impex\Atrium\Http\Controllers\ChannelUiController;
use JayI\Impex\Atrium\Http\Controllers\FlowUiController;
use JayI\Impex\Atrium\Http\Controllers\MessageUiController;
use JayI\Impex\Atrium\Http\Controllers\RunUiController;
use JayI\Impex\Domains\Flow\Models\FlowOverrideModel;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Models\RunModel;

/**
 * Registers Impex inside the Atrium dashboard.
 *
 * Widgets declared here are offered in Atrium's picker. None is ever placed
 * on a dashboard automatically; that is always a user's choice.
 *
 * Every item, widget and search source is shown only when the signed-in
 * user may do what it leads to, asked as the JSON API asks, and with
 * `impex.authorization` on, lists and counts cover only the runs they own —
 * unless `impex.atrium.show_all` makes them an operator (see ScreenAccess).
 */
class ImpexPlugin extends Plugin
{
    /**
     * Features from `impex.atrium.features` that switch Impex in Atrium on
     * and off as a whole. A feature class that is not installed, such as
     * ImpexSupportFeature without jayi/pennantplus, is skipped.
     *
     * @return array<int, string>
     */
    public function features(): array
    {
        return $this->featuresFromConfig('impex.atrium.features');
    }

    public function navigation(): array
    {
        return [
            NavItem::make(__('impex::impex.runs'))
                ->icon(Icons::svg('play-circle'))
                ->route('atrium.impex.runs.index')
                ->group('Impex')
                ->sort(10)
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', RunModel::class))
                ->badge(fn (): ?int => self::owned(RunModel::query())->active()->count() ?: null),

            NavItem::make(__('impex::impex.messages'))
                ->icon(Icons::svg('inbox-stack'))
                ->route('atrium.impex.messages.index')
                ->group('Impex')
                ->sort(20)
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', MessageModel::class)),

            NavItem::make(__('impex::impex.flows'))
                ->icon(Icons::svg('queue-list'))
                ->route('atrium.impex.flows.index')
                ->group('Impex')
                ->sort(30)
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', FlowOverrideModel::class)),

            // Channels have no policy: the API lets anyone signed in list them.
            NavItem::make(__('impex::impex.channels'))
                ->icon(Icons::svg('signal'))
                ->route('atrium.impex.channels.index')
                ->group('Impex')
                ->sort(40)
                ->authorize(fn (Request $request): bool => Authorizer::for(app(PackageRegistry::class)->get('impex'))->authenticated($request->user())),
        ];
    }

    public function routes(): void
    {
        Route::name('impex.')->group(function (): void {
            Route::get('impex/runs', [RunUiController::class, 'index'])->name('runs.index');
            Route::get('impex/runs/{run}', [RunUiController::class, 'show'])->name('runs.show');
            Route::post('impex/runs/{run}/cancel', [RunUiController::class, 'cancel'])->name('runs.cancel');
            Route::post('impex/runs/{run}/retry', [RunUiController::class, 'retry'])->name('runs.retry');
            Route::post('impex/runs/{run}/signals', [RunUiController::class, 'signal'])->name('runs.signal');

            Route::get('impex/messages', [MessageUiController::class, 'index'])->name('messages.index');
            Route::get('impex/messages/{message}', [MessageUiController::class, 'show'])->name('messages.show');

            Route::get('impex/flows', [FlowUiController::class, 'index'])->name('flows.index');
            Route::post('impex/flows/{flow}/runs', [FlowUiController::class, 'run'])->name('flows.run');

            Route::get('impex/channels', ChannelUiController::class)->name('channels.index');
        });
    }

    /**
     * Widget types Impex makes available.
     *
     * Returning a definition offers the widget in the picker; it does not
     * place it on anyone's dashboard.
     */
    public function widgets(): array
    {
        return [
            WidgetDefinition::make('impex.run-status')
                ->label(__('impex::impex.widget_run_status'))
                ->description(__('impex::impex.widget_run_status_description'))
                ->defaultSize(6, 2)
                ->view('impex::ui.widgets.run-status')
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', RunModel::class))
                ->resolve(fn (): array => [
                    'counts' => collect(RunStatus::cases())
                        ->mapWithKeys(fn (RunStatus $status): array => [
                            $status->value => self::owned(RunModel::query())->where('status', $status)->count(),
                        ])
                        ->all(),
                ]),

            WidgetDefinition::make('impex.recent-failures')
                ->label(__('impex::impex.widget_recent_failures'))
                ->description(__('impex::impex.widget_recent_failures_description'))
                ->defaultSize(6, 2)
                ->view('impex::ui.widgets.recent-failures')
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', RunModel::class))
                ->resolve(fn (): array => [
                    'runs' => self::owned(RunModel::query())
                        ->where('status', RunStatus::Failed)
                        ->latest('finished_at')
                        ->limit(5)
                        ->get(),
                ]),

            WidgetDefinition::make('impex.message-volume')
                ->label(__('impex::impex.widget_messages'))
                ->description(__('impex::impex.widget_messages_description'))
                ->defaultSize(3, 1)
                ->view('impex::ui.widgets.message-volume')
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', MessageModel::class))
                ->resolve(fn (): array => [
                    'inbound' => self::ownedMessages()
                        ->where('direction', Direction::Inbound)
                        ->where('occurred_at', '>=', now()->subDay())
                        ->count(),
                    'outbound' => self::ownedMessages()
                        ->where('direction', Direction::Outbound)
                        ->where('occurred_at', '>=', now()->subDay())
                        ->count(),
                ]),
        ];
    }

    public function settings(): ?SettingsPanel
    {
        return SettingsPanel::make('impex')
            ->label(__('impex::impex.settings_label'))
            ->description(__('impex::impex.settings_description'))
            ->view('impex::ui.settings')
            ->resolve(fn (): array => [
                'retention' => (array) config('impex.retention', []),
                'limits' => (array) config('impex.limits', []),
                'artifacts' => (array) config('impex.artifacts', []),
                'previewBytes' => (int) config('impex.messages.preview_bytes', 0),
            ]);
    }

    public function search(): ?SearchSource
    {
        return SearchSource::make('impex')
            ->label(__('impex::impex.label'))
            ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', RunModel::class))
            ->using(fn (string $query): array => self::owned(RunModel::query())
                ->where(fn (Builder $builder): Builder => $builder
                    ->where('flow', 'like', '%'.$query.'%')
                    ->orWhere('id', 'like', $query.'%'))
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(fn (RunModel $run): SearchResult => SearchResult::make(
                    $run->flow,
                    route('atrium.impex.runs.show', $run),
                )->subtitle($run->status->value)->group(__('impex::impex.runs')))
                ->all());
    }

    /**
     * @param  class-string<Model>  $subject
     */
    private static function may(Request $request, string $ability, string $subject): bool
    {
        return ScreenAccess::allows($ability, $subject, request: $request);
    }

    /**
     * Limit runs to those the signed-in user owns, as the JSON API does,
     * while authorization is on and they are not an operator.
     *
     * @param  Builder<RunModel>  $query
     * @return Builder<RunModel>
     */
    private static function owned(Builder $query): Builder
    {
        $viewer = ScreenAccess::viewer();

        return $viewer === null ? $query : $query->whereOwnedBy($viewer);
    }

    /**
     * Messages of the runs the signed-in user owns, scoped as runs are.
     *
     * @return Builder<MessageModel>
     */
    private static function ownedMessages(): Builder
    {
        $query = MessageModel::query();
        $viewer = ScreenAccess::viewer();

        if ($viewer !== null) {
            $query->whereHas('run', fn (Builder $runs): Builder => $runs->whereOwnedBy($viewer));
        }

        return $query;
    }
}
