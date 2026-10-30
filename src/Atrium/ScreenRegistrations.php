<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Atrium;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

/**
 * What Impex adds to other packages' Atrium screens, registered from
 * `ImpexServiceProvider::boot()` while Atrium is installed and the UI is on.
 *
 * Today that is the run link: one `impex.run-link` section per entry of
 * `impex.atrium.run_link.screens` (screen key => slot), rendering the row's
 * or record's run as a link to its Atrium page. Impex names no host here;
 * the hosts come from config alone.
 *
 * A malformed entry, or one Atrium rejects, throws in local and testing,
 * where a developer will see it; anywhere else it is reported once and
 * skipped, so a cosmetic link never takes production down.
 */
final class ScreenRegistrations
{
    /**
     * The run link's section key.
     */
    public const string RUN_LINK = 'impex.run-link';

    /**
     * The page the run link opens.
     */
    private const string RUN_ROUTE = 'atrium.impex.runs.show';

    /**
     * Impex's Atrium plugin, which must be authorized for the request.
     */
    private const string PLUGIN = 'impex';

    public static function register(): void
    {
        // An Atrium older than the screen registry has nothing to register into.
        if (! class_exists(ScreenExtensions::class)) {
            return;
        }

        $screens = self::screens();

        if (! is_array($screens)) {
            self::fail(new InvalidArgumentException('[impex.atrium.run_link.screens] must be an array of screen keys and slot names, such as [\'vendor.screen\' => \'row.actions\'].'));

            return;
        }

        foreach ($screens as $target => $slot) {
            try {
                self::registerRunLink($target, $slot);
            } catch (InvalidArgumentException $exception) {
                self::fail($exception);
            }
        }
    }

    /**
     * The configured screens, or the shipped default when a config
     * published before the key existed has none. Only an explicit []
     * registers nothing.
     */
    private static function screens(): mixed
    {
        $screens = config('impex.atrium.run_link.screens');

        if ($screens !== null) {
            return $screens;
        }

        /** @var array{atrium?: array{run_link?: array{screens?: mixed}}} $shipped */
        $shipped = require dirname(__DIR__, 2).'/config/impex.php';

        return $shipped['atrium']['run_link']['screens'] ?? [];
    }

    /**
     * @throws InvalidArgumentException for a malformed entry, or one Atrium rejects
     */
    private static function registerRunLink(mixed $target, mixed $slot): void
    {
        if (! is_string($target) || $target === '' || ! is_string($slot) || $slot === '') {
            throw new InvalidArgumentException(sprintf(
                'Every [impex.atrium.run_link.screens] entry maps a screen key to a slot name, both non-empty strings; got [%s => %s].',
                self::describe($target),
                self::describe($slot),
            ));
        }

        Atrium::screens()->section($target, $slot, SectionDefinition::make(self::RUN_LINK)
            ->label(__('impex::impex.run'))
            ->view('impex::ui.screens.run-link')
            ->authorize(fn (Request $request, ?Model $record): bool => self::showsRunLink($request, $record)));
    }

    /**
     * Whether the run link shows on this record (the row, in a row slot):
     * a saved run, Impex's plugin authorized for the request (so its
     * features, and with them its run page, are on), a viewer who may view
     * the run, and the run page's route loaded, so a route-cache or
     * plugin-loading mismatch hides the link rather than failing the page.
     *
     * The record type is checked first, so other rows cost no query. Atrium
     * remembers the plugin answer per request, and the whole answer per
     * record and request.
     */
    private static function showsRunLink(Request $request, ?Model $record): bool
    {
        return $record instanceof RunModel
            && $record->exists
            && array_key_exists(self::PLUGIN, Atrium::pluginRegistry()->authorized($request))
            && ScreenAccess::allows('view', $record, [], $request)
            && Route::has(self::RUN_ROUTE);
    }

    /**
     * A config value as it reads in an error message: JSON, or its type
     * when it has none.
     */
    private static function describe(mixed $value): string
    {
        $json = json_encode($value);

        return $json === false ? get_debug_type($value) : $json;
    }

    /**
     * Throw where a developer will see it; elsewhere report and carry on.
     *
     * @throws InvalidArgumentException in the local and testing environments
     */
    private static function fail(InvalidArgumentException $exception): void
    {
        if (app()->environment('local', 'testing')) {
            throw $exception;
        }

        report($exception);
    }
}
