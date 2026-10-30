# Register Showroom run link

## Goal

Impex owns the link from any row whose record is an Impex run to that run's Atrium page. The link is placed on other packages' screens only through editable defaults in `config/impex.php`, by default Showroom's transfers rows, which replaces Showroom's inline check. Impex also declares every one of its own Atrium screens with the standard slots and tab bar. It stops using Atrium's removed history components, and it suggests Atrium instead of requiring it.

## Acceptance criteria

### Run link on other packages' screens

1. **Config.** `config/impex.php` gains `atrium.run_link.screens` under the existing `atrium` section, with a banner. It defaults to `['showroom.transfers' => 'row.actions']`. Keys are screen keys and values are slot names (a row slot or a normal slot); there is no tab form. A config test reads the default. No Impex PHP code names a Showroom class or screen: a test asserts `src/` contains no `RefactorCircus\Showroom` and no `showroom.transfers` (the existing doc example `showroom.products` in `Stream.php` is a stream name and stays).
2. **Registration.** While Atrium is installed and `impex.ui.enabled` is true, Impex registers, from `boot()`, `Atrium::screens()->section($target, $slot, SectionDefinition::make('impex.run-link')->label(__('impex::impex.run'))->view('impex::ui.screens.run-link')->authorize(...))` once per config entry. It has no resolver: the view reads the host's `record`. An empty list registers nothing. A feature test reads `ScreenExtensions::sections('showroom.transfers', 'row.actions')` for the default. It also asserts that a custom list (`['acme.jobs' => 'aside']`) registers only that entry, and that `impex.ui.enabled = false` registers nothing.
3. **Visibility.** The section is visible only when all of these hold. A feature test covers each case being false on its own:
   - The record (the row, in a row slot) is a saved `RunModel` (`$record instanceof RunModel && $record->exists`).
   - Impex's Atrium plugin is authorized for the request: `PluginRegistry::authorized($request)` has the key `impex`, so its features and its `atrium.impex.runs.show` route are on.
   - `ScreenAccess::allows('view', $record, [], $request)` passes. This includes the operator rule (`impex.atrium.show_all`).

   The closure checks the record type first and makes no query for other rows.
4. **Rendering.** `impex::ui.screens.run-link` renders `<a href="{{ route('atrium.impex.runs.show', $record) }}" data-testid="impex-run-link">{{ $record->flow }}</a>`, styled with the classes Showroom uses today (`underline-offset-2 hover:underline`), and nothing else. `StylesTest` covers the view.
5. **Test host.** The tests use a workbench test-host screen, not Showroom as a dev dependency:
   - `Workbench\App\Http\Controllers\TestHostScreenController` serves `GET test-host/{key}` with `web` middleware, registered by the test case's `defineRoutes()`.
   - It declares `Screen::standard($key, Screen::LIST)` with a Gate-based `access()`, resolves it, and renders a workbench view.
   - The view renders `x-atrium::screen-tabs`, `screen-tab-panel`, the `header.actions`, `before` and `after` sections, and a table whose rows are given by the test. Each row renders `<x-atrium::screen-sections :screen="$resolved" slot="row.actions" :row="$row" />`.

   Tests key it `showroom.transfers`. `composer.json` gains no Showroom dependency (asserted by criterion 14's test).
6. **Behaviour.** `tests/Feature/Ui/RunLinkSectionTest.php`, on the test host keyed `showroom.transfers`, with rows of two runs the viewer may view, one run they may not, and one workbench `User` row, asserts:
   - each viewable run's row has one `impex-run-link` to its `atrium.impex.runs.show` URL with its flow name escaped (a flow name containing markup is tested)
   - the run the viewer may not view and the `User` row have no link
   - with `impex.atrium.show_all = true` and authorization on, an operator sees the link on a run they don't own
   - with Impex's support feature off (Pennant mode test in `tests/PennantPlus`), no link renders

### Impex's own screens

7. **Declared screens.** Every Impex page with a controller view declares itself with `Screen::standard()` and resolves it after its own authorization. Each has only the synthetic `overview` tab built in:

   | Key | Route (`atrium.impex.`) | Kind | Record | Subject class for `access()` when the record is null | `row.actions` row model |
   |---|---|---|---|---|---|
   | `impex.runs` | `runs.index` | list | none | `RunModel` | each `RunModel` |
   | `impex.run` | `runs.show` | detail | the `RunModel` | | |
   | `impex.messages` | `messages.index` | list | none | `MessageModel` | each `MessageModel` |
   | `impex.message` | `messages.show` | detail | the `MessageModel` | | |
   | `impex.flows` | `flows.index` | list | none | `FlowOverrideModel` | the flow's stored `FlowOverrideModel`, else `new FlowOverrideModel(['slug' => $slug])` (stored overrides fetched in one query keyed by slug) |
   | `impex.channels` | `channels.index` | list | none | `ChannelModel` | the stored `ChannelModel`, else `new ChannelModel(['name' => $name])` (one query keyed by name) |
   | `impex.channel-create` | `channels.create` | detail | none | `ChannelModel` | |
   | `impex.channel` | `channels.show` | detail | `$stored`, else `new ChannelModel(['name' => $name])` | | |
   | `impex.subscriptions` | `subscriptions.index` | list | none | `SubscriptionModel` | each `SubscriptionModel` |
   | `impex.subscription` | `subscriptions.show` | detail | the `SubscriptionModel` | | |

   A feature test with a dataset over all 10 screens registers a tab and a section in every standard slot (`header.actions`, `before`, `after`, plus `aside` on detail or `row.actions` on list screens, with at least one row present). It asserts each section renders in its slot and not in an undeclared one, and that the tab appears and opens through `?tab=`.
8. **Access closure.** Each screen's `access()` is `fn (string $permission, Request $request, ?Model $record): bool => ScreenAccess::allows($permission, $record ?? $subject, [], $request)`, with `$subject` from the table. Tests:
   - a `permission('view')` section on `impex.run` shows for the run's owner and hides for another user with authorization on
   - it shows for an operator
   - a `permission('viewAny')` section on `impex.runs` follows the class check
9. **Layout.** Per Atrium criterion 15, an open registered tab's panel replaces the page content, and only `header.actions` stays. `header.actions` sections render inside `x-atrium::page-header`'s actions, after the page's own actions. `before` and `after` render around the content. `aside` renders in a right-hand column only when it has visible sections. `row.actions` renders inside each row's last cell, after its own controls. With nothing registered, the markup is unchanged: every existing feature test passes without modification, except those listed in criterion 11.

### History removal and Atrium as a suggestion

10. **No history in Impex's UI.** The `<x-atrium::audit-trail>` calls in `ui/runs/index`, `ui/runs/show` and `ui/messages/show` are removed, and `ImpexPlugin::navigation()` no longer calls `historyNavItem()`. Screens declare no audit subject (Keen attaches History by wildcard). Impex's JSON history route and MCP history tool are unaffected (`tests/Feature/HistoryTest.php` passes unchanged). A test asserts that the plugin's navigation has no item whose route is `atrium.history.show`. A grep of `resources`, `src`, `README.md`, `CLAUDE.md` and `docs` (excluding `docs/agent/specs`) for `audit-trail` and `historyNavItem` finds nothing. `docs/11-dashboard.md` and the Boost skill are updated to say that History comes from Keen.
11. **Forced test changes.** Existing tests may change only where this spec's decisions force it, and the PR lists each by file and test name. The expected set is:
    - `tests/Feature/Ui/AuditTrailTest.php`: deleted.
    - `tests/Feature/HistoryNavigationTest.php`, `links its own audit log from its sidebar group`: replaced by criterion 10's assertion.
    - `tests/Pest.php` and `tests/TestCase.php`: gain `ResetsScreenExtensions` and the test-host route.
12. **Composer.** `refactor-circus/atrium` moves from `require` to `require-dev`, and it is added to `suggest` with a description. Keystone stays the only suite package in `require`. A test reads `composer.json` and asserts:
    - `require` has no `refactor-circus/*` key other than `refactor-circus/keystone`
    - `suggest` has `refactor-circus/atrium`
    - `require-dev` has `refactor-circus/atrium`
13. **Arch test.** `tests/ArchTest.php` gains `arch('only the Atrium layer uses Atrium')->expect('RefactorCircus\Impex')->not->toUse('RefactorCircus\Atrium')->ignoring('RefactorCircus\Impex\Atrium')`, and it passes. Registration lives in `RefactorCircus\Impex\Atrium\ScreenRegistrations`, reached from `ImpexServiceProvider` only behind the same string `class_exists('RefactorCircus\Atrium\Atrium')` and `impex.ui.enabled` guard that `registerAtriumPlugin()` uses.
14. **No package names.** A test asserts `composer.json` lists no `refactor-circus/showroom`.
15. **No-Atrium proof.** A `WithoutAtrium` suite proves Impex boots and serves its HTTP API and MCP without Atrium:
    - **Files:** `phpunit.without-atrium.xml`, bootstrap `tests/WithoutAtrium/bootstrap.php`, tests in `tests/WithoutAtrium`, excluded from the default suite. The bootstrap removes the `RefactorCircus\Atrium\` PSR-4 prefix from Composer's loader. Its test case registers no Atrium provider.
    - **Assertions:**
      - `class_exists('RefactorCircus\Atrium\Atrium')` is false
      - the app boots with `ImpexServiceProvider` (including the `@impexCan` registration, which must not load an Atrium class)
      - no route name starts with `atrium.`
      - the JSON API starts a workbench flow and lists runs
      - an MCP run-listing tool answers
    - **Gate:** a composer script `test:without-atrium` runs it, and `composer test` calls it after `@test:unit`.
    - **CI:** `.github/workflows/tests.yml` gains a `without-atrium` job that runs `composer remove --dev refactor-circus/atrium --no-update`, installs, and runs `composer test:without-atrium`.

### Documentation

16. **Docs.** Two places change:
    - **README and `docs/11-dashboard.md`:** document `impex.atrium.run_link.screens`, the `impex.run-link` section key, the visibility rules from criterion 3, and that Atrium is suggested, not required.
    - **`CHANGELOG.md`:** an upgrade note covering:
      - Atrium moved to `suggest`
      - the new config key
      - removed history UI (Keen provides it)
      - grouped merge with Atrium, Keen and Showroom

    A docs test asserts the README mentions `run_link.screens`, `impex.run-link` and `suggest`.
17. Done means `composer test` passes, including `test:without-atrium`. Impex has no browser suite, so there is no `test:browser` step.

## Design

**Placement.** `RefactorCircus\Impex\Atrium\ScreenRegistrations` (final, static `register(): void`) reads `impex.atrium.run_link.screens` and calls `Atrium::screens()->section()` per entry. Atrium validates targets and slots, so a malformed entry throws `InvalidArgumentException` at boot. `ImpexServiceProvider::boot()` calls it from a private `registerAtriumScreens()` right after `registerAtriumPlugin()`, with the same guard. A target nobody declares never renders.

**Why a section, not a check in the host.** Showroom's own spec removes its inline `Route::has(...) && class_exists(ScreenAccess::class)` check (Showroom `transfers/index.blade.php:62`) and renders `row.actions` per run row. The flow name stays as plain text in Showroom's first cell. Impex adds the link in the row slot. The link text is the flow name, as today.

**Authorization.** Impex's own `Atrium\ScreenAccess::allows()` decides, so the operator rule and `impex.authorization` work as they do on Impex's own screens. The host's authorization has already run before `resolve()`.

**Screen helper.** `RefactorCircus\Impex\Atrium\Screens::declare(string $key, string $kind, ?Model $record, ?string $subject): Screen` (final, static) builds `Screen::standard()`, sets the record, and adds the access closure from criterion 8. Controllers inject Atrium's `ScreenExtensions` and pass `resolved` to their views.

**Arch.** No new models, Data or Actions. The row stand-ins are unsaved instances of existing models, which Atrium memoizes per instance (Atrium criterion 8). The new classes sit under `src/Atrium`, which the model and Data rules don't scan. The `@impexCan` Blade condition is registered as a closure and loads no Atrium class until a view calls it.

**Lang.** The section label reuses the existing `impex::impex.run` ("Run").

## Out of scope

- Showroom's changes (removing its inline check, declaring `showroom.transfers` with `row.actions`): Showroom's `declare-screen-extensions` spec.
- Roster's and Showroom's transfers pages and nav (D-002).
- History and audit UI (Keen's spec).
- A tab form of the run link.

## Risks and open questions

- **Q-1. Running without Atrium.** This spec runs it in process (the PSR-4 prefix removed, inside `composer test`) and also in a CI lane with Atrium uninstalled. Options: in-process only, CI only, or both. Recommendation: both. It is the same question as Cortex's Q-2, so answer it once for the suite.
- **Q-2. Link placement.** The link is now a separate row-slot cell or fragment rather than the flow-name cell itself, so Showroom's first column shows plain text with the link beside it. Options: (a) accept that; (b) Showroom declares a dedicated row slot on its first cell (e.g. `row.title`) and the default config targets it. Recommendation: (a), because it keeps the standard slots only, and the config lets an app move it if Showroom adds such a slot.
- **Risks:**
  - Grouped merge: Atrium PR #16 removes the history components, and Showroom drops its inline link. Merge Atrium, Keen, Showroom and Impex together, or the link disappears in between.
  - Moving Atrium to `require-dev` breaks apps that relied on Impex pulling it in. The CHANGELOG says so.
