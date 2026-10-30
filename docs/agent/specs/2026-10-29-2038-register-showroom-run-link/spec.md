# Register Showroom run link

## Goal

This change has three workstreams. They ship in one PR, together with the grouped merge of Atrium, Keen and Showroom (see Risks): Atrium's history components are removed, Showroom drops its inline link, and Keen provides History, so none of the three can land on Impex alone without breaking a screen or losing the link.

1. **Run link (criteria 1-6).** Impex owns the link from any row whose record is an Impex run to that run's Atrium page. It is placed on other packages' screens only through editable defaults in `config/impex.php`, by default Showroom's transfers rows, which replaces Showroom's inline check.
2. **Screen declarations (criteria 7-9).** Impex declares every one of its own 10 Atrium screens with the standard slots and tab bar (the standard-slots decision, Atrium D-013). This is the largest part of the change.
3. **Decoupling from Atrium (criteria 10-14).** Impex stops using Atrium's removed history components, suggests Atrium instead of requiring it, refuses an Atrium older than the screen registry through a composer `conflict`, and proves it runs without Atrium both in process and in a CI lane (D-020, D-022).

The workstreams ship as one spec and one PR (D-024): the screen declarations and the run link both need the registry, and the removal of history and the move to `suggest` are only safe once Keen supplies History. Criteria 15-16 cover docs and the done gate for all three. A malformed integration config entry throws in local and testing and is reported and skipped in production (D-021). The run link is a separate `row.actions` fragment beside Showroom's plain-text flow name, using the standard slots only (D-023, D-026, D-029).

## Acceptance criteria

### Run link on other packages' screens

1. **Config.** `config/impex.php` gains `atrium.run_link.screens` under the existing `atrium` section, with a banner. It defaults to `['showroom.transfers' => 'row.actions']`. Keys are screen keys and values are slot names (a row slot or a normal slot); there is no tab form. A config test reads the default. No Impex PHP code names a Showroom class or screen: a test asserts the code in `src/` (comments excluded) contains no `RefactorCircus\Showroom` and no `showroom.transfers` (the existing doc example `showroom.products` in `Stream.php` is a stream name and stays).
2. **Registration.** While Atrium is installed and `impex.ui.enabled` is true, Impex registers, from `boot()`, `Atrium::screens()->section($target, $slot, SectionDefinition::make('impex.run-link')->label(__('impex::impex.run'))->view('impex::ui.screens.run-link')->authorize(...))` once per config entry. It has no resolver: the view reads the host's `record`. A missing key (`null`, as in an app that published `config/impex.php` before this change) uses the shipped default; only an explicit `[]` registers nothing. Each entry is validated in `ScreenRegistrations` (non-empty string key, non-empty string slot). Per the suite rule (D-021, same as Atrium D-022), an invalid entry, or one Atrium rejects with `InvalidArgumentException`, throws `InvalidArgumentException` from `boot()` in the `local` and `testing` environments; in any other environment (production) it is reported once with `report()` and skipped, the remaining entries still register, and boot completes. Registration also requires `class_exists('RefactorCircus\Atrium\ScreenExtensions')`, so an Atrium older than the registry registers nothing instead of fataling. A feature test reads `ScreenExtensions::sections('showroom.transfers', 'row.actions')` for the default. It also asserts that a custom list (`['acme.jobs' => 'aside']`) registers only that entry, and that `impex.ui.enabled = false` registers nothing. Further tests: unsetting `impex.atrium.run_link` still yields the `showroom.transfers` entry; in `testing`, a malformed entry (an integer key, a `null` or empty slot, an unknown slot) throws `InvalidArgumentException`; with the environment set to `production` and `Exceptions::fake()`, a map holding one malformed and one valid entry boots without throwing, reports exactly one `InvalidArgumentException`, and registers the valid entry.
3. **Visibility.** The section is visible only when all of these hold. A feature test covers each case being false on its own:
   - The record (the row, in a row slot) is a saved `RunModel` (`$record instanceof RunModel && $record->exists`).
   - Impex's Atrium plugin is authorized for the request: `PluginRegistry::authorized($request)` has the key `impex`, so its features and its `atrium.impex.runs.show` route are on.
   - `ScreenAccess::allows('view', $record, [], $request)` passes. This includes the operator rule (`impex.atrium.show_all`).
   - `Route::has('atrium.impex.runs.show')` is true, so a route-cache or plugin-loading mismatch hides the link instead of causing a 500.

   The closure checks the record type first and makes no query for other rows. The plugin-authorized check is memoized per request. A feature test with about 25 viewable run rows asserts a constant number of queries from the section (independent of the row count), and that the `view` policy check loads no relations. Tests cover each case false on its own: record not a `RunModel`, record unsaved, plugin not authorized for the request, `ScreenAccess` refuses, and route absent.
4. **Rendering.** `impex::ui.screens.run-link` renders `<a href="{{ route('atrium.impex.runs.show', $record) }}" data-testid="impex-run-link">{{ $record->flow }}</a>`, styled with the classes Showroom uses today (`underline-offset-2 hover:underline`), and nothing else. `StylesTest` covers the view. A view render test asserts the exact anchor HTML for a given run, including a flow name containing markup, which is escaped.
5. **Test host.** The tests use a workbench test-host screen, not Showroom as a dev dependency:
   - `Workbench\App\Http\Controllers\TestHostScreenController` serves `GET test-host/{key}` with `web` middleware, registered by the test case's `defineRoutes()`.
   - It declares `Screen::standard($key, Screen::LIST)` with a Gate-based `access()`, resolves it, and renders a workbench view.
   - The view renders `x-atrium::screen-tabs`, `screen-tab-panel`, the `header.actions`, `before` and `after` sections, and a table whose rows are given by the test. Each row renders `<x-atrium::screen-sections :screen="$resolved" slot="row.actions" :row="$row" />`.

   Tests key it `showroom.transfers`. `composer.json` gains no Showroom dependency (asserted by criterion 12's test).
   The link sits in its own `row.actions` fragment, beside the host's plain-text flow name; no dedicated title slot is asked of the host (D-023, D-026, D-029).
6. **Behaviour.** `tests/Feature/Ui/RunLinkSectionTest.php`, on the test host keyed `showroom.transfers`, with rows of two runs the viewer may view, one run they may not, and one workbench `User` row, asserts:
   - each viewable run's row has one `impex-run-link` to its `atrium.impex.runs.show` URL with its flow name escaped (a flow name containing markup is tested)
   - the run the viewer may not view and the `User` row have no link
   - with `impex.atrium.show_all = true` and authorization on, an operator sees the link on a run they don't own
   - with the `impex.atrium.features` entry that gates `atrium.impex.runs.show` turned off (the key is named in `config/impex.php`; the test sets it to `false` through config and lives in the default suite), no link renders, and the same holds when the plugin is not authorized for the request
   - the Pennant variant of the same case runs in `tests/PennantPlus` by switching that feature off through Pennant, and is the only case in this list that does

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

   Rows of `row.actions` for flows and channels use these stand-ins because the standard-slots decision (Atrium D-013) requires every screen to declare its standard slots, not because the run link needs them; stored rows come from the single keyed query noted in the table. The stand-ins are unsaved (`exists` is false, documented in the README), `slug` and `name` must be fillable or the stand-ins are built with `forceFill`, and a test asserts rendering a stand-in row fires no model events and issues no queries.

   A feature test with a dataset over all 10 screens (the dataset table lists route, fixture and expected slots per screen: a run, a message, a subscription, a stored channel, and a flow with and without an override, as each route needs; list screens have at least one row) registers a tab and a section in every standard slot (`header.actions`, `before`, `after`, plus `aside` on detail or `row.actions` on list screens, with at least one row present). It asserts each section's marker string renders in its slot's region and is absent from the other slots' regions, that registering into an undeclared slot throws `InvalidArgumentException`, and that the tab appears and, opened through `?tab=`, its panel replaces the page content (the tab marker present, the content marker absent).
8. **Access closure.** Each screen's `access()` is `fn (string $permission, Request $request, ?Model $record): bool => ScreenAccess::allows($permission, $record ?? $subject, [], $request)`, with `$subject` from the table. Tests:
   - a `permission('view')` section on `impex.run` shows for the run's owner and hides for another user with authorization on
   - it shows for an operator
   - a `permission('viewAny')` section on `impex.runs` follows the class check
9. **Layout.** Per Atrium criterion 15, an open registered tab's panel replaces the page content, and only `header.actions` stays. `header.actions` sections render inside `x-atrium::page-header`'s actions, after the page's own actions. `before` and `after` render around the content. `aside` renders in a right-hand column only when it has visible sections. `row.actions` renders inside each row's last cell, after its own controls. Tests in `tests/Feature/Ui/ScreenLayoutTest.php` use `assertSeeInOrder` on `data-testid` markers for header actions (after the page's own actions), `before`, content, `after`, and the row's last cell (after its own controls). A further test asserts the aside column is not rendered when it has no visible sections. With nothing registered, the markup is unchanged: every existing feature test passes without modification, except those listed in criterion 11, and a test asserts the registered section count is zero for the default test state.

### History removal and Atrium as a suggestion

10. **No history in Impex's UI.** The `<x-atrium::audit-trail>` calls in `ui/runs/index`, `ui/runs/show` and `ui/messages/show` are removed, and `ImpexPlugin::navigation()` no longer calls `historyNavItem()`. Screens declare no audit subject (Keen attaches History by wildcard). Impex's JSON history route and MCP history tool are unaffected (`tests/Feature/HistoryTest.php` passes unchanged). A test asserts that the plugin's navigation has no item whose route is `atrium.history.show`. A grep of `resources`, `src`, `README.md`, `CLAUDE.md` and `docs` (excluding `docs/agent/specs`) for `audit-trail` and `historyNavItem` finds nothing; the scan list is exactly these paths, so the test never scans itself. `docs/11-dashboard.md` and the Boost skill are updated to say that History comes from Keen (a test asserts `docs/11-dashboard.md` contains `Keen`). The loss is permanent for apps with Atrium but without Keen, so the `suggest` description and the upgrade note say to install `refactor-circus/keen` to keep History.
11. **Forced test changes.** Existing tests may change only where this spec's decisions force it, and the PR lists each by file and test name. The expected set is:
    - `tests/Feature/Ui/AuditTrailTest.php`: deleted.
    - `tests/Feature/HistoryNavigationTest.php`, `links its own audit log from its sidebar group`: replaced by criterion 10's assertion.
    - `tests/Pest.php` and `tests/TestCase.php`: gain `ResetsScreenExtensions` and the test-host route.
12. **Composer.** `refactor-circus/atrium` moves from `require` to `require-dev` (keeping its current `dev-main` constraint), and it is added to `suggest` with a description. Keystone stays the only suite package in `require`. `composer.json` gains a `conflict` entry for `refactor-circus/atrium` on versions older than the first release that contains the screen-extensions registry (Atrium PR #16), so a mismatched install fails at `composer update` (D-020). The constraint is `<V`, where V is that Atrium release; Atrium has no tag yet, so the builder takes V from Atrium's release at grouped-merge time and, if none is tagged when the group is built, uses `<1.0.0` and records that in the PR. A test reads `composer.json` and asserts:
    - `require` has no `refactor-circus/*` key other than `refactor-circus/keystone`
    - `suggest` has `refactor-circus/atrium`, its description saying the screen-extensions registry release is the minimum and mentioning Keen for History
    - `require-dev` has `refactor-circus/atrium`
    - `conflict` has a non-empty `refactor-circus/atrium` constraint starting with `<`
    - none of `require`, `require-dev` and `suggest` lists `refactor-circus/showroom` (this is the only Showroom-dependency check)
13. **Arch test.** `tests/ArchTest.php` gains `arch('only the Atrium layer uses Atrium')->expect('RefactorCircus\Impex')->not->toUse('RefactorCircus\Atrium')->ignoring('RefactorCircus\Impex\Atrium')`, and it passes. Registration lives in `RefactorCircus\Impex\Atrium\ScreenRegistrations`, reached from `ImpexServiceProvider` only behind the same string `class_exists('RefactorCircus\Atrium\Atrium')` and `impex.ui.enabled` guard that `registerAtriumPlugin()` uses.
14. **No-Atrium proof, both ways (D-020, D-022, D-025, D-028).** A `WithoutAtrium` suite inside `composer test` proves Impex boots and serves its HTTP API and MCP without Atrium, and a CI lane proves it with Atrium uninstalled. The in-process run cannot see classmap or Composer `files` leaks, so the CI lane is the authoritative proof and is verified only by the workflow run, not by `composer test`; the README says so.
    - **Files:** `phpunit.without-atrium.xml`, bootstrap `tests/WithoutAtrium/bootstrap.php`, tests in `tests/WithoutAtrium`, excluded from the default suite. The bootstrap removes the `RefactorCircus\Atrium\` PSR-4 prefix from Composer's loader. Its test case registers no Atrium provider, disables Testbench package discovery for Atrium (an explicit provider list), and loads no workbench Atrium code (the test-host controller and any workbench provider referencing Atrium are excluded). The bootstrap runs before the autoloader first loads an Atrium class.
    - **Assertions:**
      - `class_exists('RefactorCircus\Atrium\Atrium')` is false, and the `RefactorCircus\Atrium\` prefix is absent from Composer's loader
      - the Atrium provider is not in `app()->getLoadedProviders()`
      - the app boots with `ImpexServiceProvider` (including the `@impexCan` registration, which must not load an Atrium class)
      - no route name starts with `atrium.`
      - the JSON API starts a workbench flow and lists runs
      - the runs list MCP tool answers, called through `mcpTool()` so its output schema is checked
    - **Gate:** a composer script `test:without-atrium` runs it, and `composer test` calls it after `@test:unit`.
    - **CI:** `.github/workflows/tests.yml` gains a `without-atrium` job that removes any dev dependency that requires Atrium, runs `composer remove --dev refactor-circus/atrium --no-update` then `composer update` (to avoid lock drift), and runs `composer test:without-atrium`.

### Documentation

15. **Docs.** Every doc file touched is listed here:
    - **README, `docs/11-dashboard.md` and the Boost skill:** document `impex.atrium.run_link.screens`, the `impex.run-link` section key, the visibility rules from criterion 3, the malformed-entry rule from criterion 2, and that Atrium is suggested, not required. The README also says the CI lane is the authoritative no-Atrium proof (criterion 14).
    - **`CLAUDE.md`:** the Atrium screens section drops the `<x-atrium::audit-trail>` line and the `historyNavItem` mention.
    - **`CHANGELOG.md`:** an upgrade note covering:
      - Atrium moved to `suggest`, with the minimum Atrium version, and a `conflict` refusing older Atrium
      - a malformed `run_link.screens` entry throws at boot in local and testing, and is reported and skipped in production
      - republish `config/impex.php` to see the new key (a missing key falls back to the default)
      - install `refactor-circus/keen` to keep History
      - the new config key
      - removed history UI (Keen provides it)
      - grouped merge with Atrium, Keen and Showroom

    A docs test asserts the README mentions `run_link.screens`, `impex.run-link` and `suggest`, and the CHANGELOG contains `suggest`, `conflict`, `run_link.screens`, `Keen` and `Showroom`. The PR's list of changed tests (criterion 11) is a manual verification item.
16. Done means `composer test` passes, including `test:without-atrium`, and `vendor/bin/pest` passes. Impex has no browser suite, so there is no `test:browser` step.

## Design

**Placement.** `RefactorCircus\Impex\Atrium\ScreenRegistrations` (final, static `register(): void`) reads `impex.atrium.run_link.screens` and calls `Atrium::screens()->section()` per entry. Atrium validates targets and slots and throws `InvalidArgumentException` for a malformed one. `ScreenRegistrations` validates and registers each entry inside its own `try`; the `catch` rethrows when `app()->environment('local', 'testing')`, and otherwise calls `report($e)` and moves on, so production never goes down over a cosmetic link (criterion 2, D-021). `ImpexServiceProvider::boot()` calls it from a private `registerAtriumScreens()` right after `registerAtriumPlugin()`, with the same guard. A target nobody declares never renders.

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
- Atrium's registry and slot rendering (`ScreenExtensions`, `Screen::standard`, `ResetsScreenExtensions`, the tab and slot rendering contract, Atrium criteria 8 and 15): a dependency, not changed here.
- The `history` standard slot: Keen attaches History by wildcard.
- Hosts other than Showroom: an app wires them through `impex.atrium.run_link.screens`.

## Risks

- **Grouped merge.** Atrium PR #16 removes the history components, and Showroom drops its inline link. Merge Atrium, Keen, Showroom and Impex together, or the link disappears in between (D-024).
- **Moving Atrium to `require-dev`** breaks apps that relied on Impex pulling it in. The `conflict` refuses an Atrium older than the registry, registration is also guarded on `ScreenExtensions`, and the `suggest` text and CHANGELOG state the minimum version.
- **Atrium version for `conflict`.** Atrium has no release tag yet; the constraint needs the version that ships PR #16 (criterion 12). If it is not tagged when the group is built, `<1.0.0` is used and the PR records it, so Atrium's registry release must be 1.0.0 or later.
- **Production config errors.** A malformed `run_link.screens` entry is skipped apart from one report in production, so that link is missing until the report is acted on.
- **Link placement.** Showroom's first column shows the flow name as plain text with the link in a separate `row.actions` fragment (D-026); an app can move it through config if Showroom later adds another slot.

## Changelog

- 2026-10-29, approved: folded D-020 (both-ways no-Atrium proof and the Atrium `conflict`, criteria 12 and 14), D-021 (throw in local/testing, report and skip in production, criterion 2 and Design), D-022, D-025 and D-028 (both proofs), D-023, D-026 and D-029 (separate `row.actions` fragment, standard slots only), D-024 (one spec and PR). D-027 (skip and log, rethrow only in debug) is superseded by the suite rule D-021. The merged placeholder criterion 14 was removed and criteria 15 to 17 renumbered 14 to 16. Open questions Q-1 to Q-3 removed as decided.
