<?php

declare(strict_types=1);

namespace JayI\Impex\Atrium;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use JayI\Atrium\Navigation\NavItem;
use JayI\Atrium\Plugins\Plugin;
use JayI\Atrium\Search\SearchResult;
use JayI\Atrium\Search\SearchSource;
use JayI\Atrium\Settings\SettingsPanel;
use JayI\Atrium\Support\Icons;
use JayI\Atrium\Widgets\WidgetDefinition;
use JayI\Impex\Access\Authorizer;
use JayI\Impex\Enums\Direction;
use JayI\Impex\Enums\RunStatus;
use JayI\Impex\Http\Ui\ChannelUiController;
use JayI\Impex\Http\Ui\FlowUiController;
use JayI\Impex\Http\Ui\MessageUiController;
use JayI\Impex\Http\Ui\RunUiController;
use JayI\Impex\Http\Ui\ScreenAccess;
use JayI\Impex\Models\FlowOverride;
use JayI\Impex\Models\Message;
use JayI\Impex\Models\Run;
use Throwable;

/**
 * Registers Impex inside the Atrium dashboard.
 *
 * Widgets declared here are offered in Atrium's picker. None is ever placed
 * on a dashboard automatically; that is always a user's choice.
 *
 * Every item, widget and search source is shown only when the signed-in
 * user may do what it leads to, asked as the JSON API asks, and with
 * `impex.authorization` on, lists and counts cover only the runs they own.
 */
class ImpexPlugin extends Plugin
{
    public function key(): string
    {
        return 'impex';
    }

    public function label(): string
    {
        return 'Impex';
    }

    /**
     * Features from `impex.atrium.features` that switch Impex in Atrium on
     * and off as a whole. A feature class that is not installed, such as
     * ImpexSupportFeature without jayi/pennantplus, is skipped.
     *
     * @return array<int, string>
     */
    public function features(): array
    {
        $features = config('impex.atrium.features', []);

        return array_values(array_filter(
            is_array($features) ? $features : [],
            fn (mixed $feature): bool => is_string($feature) && (! str_contains($feature, '\\') || self::installed($feature)),
        ));
    }

    public function navigation(): array
    {
        return [
            NavItem::make(__('impex::impex.runs'))
                ->icon(Icons::svg('play-circle'))
                ->route('atrium.impex.runs.index')
                ->group('Impex')
                ->sort(10)
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', Run::class))
                ->badge(fn (): ?int => self::owned(Run::query())->active()->count() ?: null),

            NavItem::make(__('impex::impex.messages'))
                ->icon(Icons::svg('inbox-stack'))
                ->route('atrium.impex.messages.index')
                ->group('Impex')
                ->sort(20)
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', Message::class)),

            NavItem::make(__('impex::impex.flows'))
                ->icon(Icons::svg('queue-list'))
                ->route('atrium.impex.flows.index')
                ->group('Impex')
                ->sort(30)
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', FlowOverride::class)),

            // Channels have no policy: the API lets anyone signed in list them.
            NavItem::make(__('impex::impex.channels'))
                ->icon(Icons::svg('signal'))
                ->route('atrium.impex.channels.index')
                ->group('Impex')
                ->sort(40)
                ->authorize(fn (Request $request): bool => app(Authorizer::class)->authenticated($request->user())),
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
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', Run::class))
                ->resolve(fn (): array => [
                    'counts' => collect(RunStatus::cases())
                        ->mapWithKeys(fn (RunStatus $status): array => [
                            $status->value => self::owned(Run::query())->where('status', $status)->count(),
                        ])
                        ->all(),
                ]),

            WidgetDefinition::make('impex.recent-failures')
                ->label(__('impex::impex.widget_recent_failures'))
                ->description(__('impex::impex.widget_recent_failures_description'))
                ->defaultSize(6, 2)
                ->view('impex::ui.widgets.recent-failures')
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', Run::class))
                ->resolve(fn (): array => [
                    'runs' => self::owned(Run::query())
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
                ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', Message::class))
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
            ->authorize(fn (Request $request): bool => self::may($request, 'viewAny', Run::class))
            ->using(fn (string $query): array => self::owned(Run::query())
                ->where(fn (Builder $builder): Builder => $builder
                    ->where('flow', 'like', '%'.$query.'%')
                    ->orWhere('id', 'like', $query.'%'))
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(fn (Run $run): SearchResult => SearchResult::make(
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
        return app(Authorizer::class)->can($request->user(), $ability, $subject);
    }

    /**
     * Limit runs to those the signed-in user owns, as the JSON API does,
     * while authorization is on.
     *
     * @param  Builder<Run>  $query
     * @return Builder<Run>
     */
    private static function owned(Builder $query): Builder
    {
        $actor = ScreenAccess::actor();

        return $actor === null ? $query : $query->whereOwnedBy($actor);
    }

    /**
     * Messages of the runs the signed-in user owns, while authorization is on.
     *
     * @return Builder<Message>
     */
    private static function ownedMessages(): Builder
    {
        $query = Message::query();

        if (ScreenAccess::actor() !== null) {
            $query->whereHas('run', fn (Builder $runs): Builder => self::owned($runs));
        }

        return $query;
    }

    /**
     * Whether a class can be loaded. A feature whose parent belongs to a
     * package that is not installed throws rather than answering false.
     */
    private static function installed(string $class): bool
    {
        try {
            return class_exists($class);
        } catch (Throwable) {
            return false;
        }
    }
}
