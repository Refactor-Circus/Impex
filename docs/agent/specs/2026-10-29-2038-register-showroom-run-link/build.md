# Build: Register Showroom run link

## Group 1 (salvaged)

**Found.** No index lock and no merge or rebase in progress. The first build agent left uncommitted work for tasks 1.1 to 1.4 and most of 1.5: `composer.json` (Atrium moved to `require-dev` at `dev-main`, its `suggest` text naming the 0.1.0 screen-extensions registry release and Keen for History, `conflict` `"refactor-circus/atrium": "<0.1.0"`, the `test:without-atrium` script called by `composer test` after `@test:unit`), the arch rule `only the Atrium layer uses Atrium`, `TestHostScreenController` with the `test-host` view, the `test-host` route in `tests/TestCase.php::defineRoutes()`, `ResetsScreenExtensions` on every Atrium-booting suite in `tests/Pest.php`, `tests/Feature/ComposerTest.php`, and `tests/WithoutAtrium/` (bootstrap, `Pest.php`, test case, `WithoutAtriumTest.php`). `composer.local.json` (git-ignored) already mirrored the composer changes by hand. It was not regenerated.

**Completed and committed.**
- `phpunit.without-atrium.xml` (bootstrap `tests/WithoutAtrium/bootstrap.php`, one `WithoutAtrium` suite, its own cache directory).
- `phpunit.xml.dist`: a comment saying `tests/WithoutAtrium` is deliberately not a suite. No configured suite covers that folder, so it is already left out.
- `.github/workflows/tests.yml`: a `without-atrium` job. It installs, removes every root dev dependency that requires Atrium directly or indirectly (computed with `composer why --recursive`; today that is `refactor-circus/cortex`), runs `composer remove --dev refactor-circus/atrium --no-update` and then `composer update`, checks that `vendor/refactor-circus/atrium` is gone, and runs `composer test:without-atrium`. It skips `composer run prepare` because `testbench.yaml` names `AtriumServiceProvider`.
- The whole group, including the first agent's files above.

**Outside the listed files.** I also committed `tests/Feature/Ui/TestHostScreenTest.php` (the test-host smoke test that task 1.1 asks for) and `workbench/resources/views/test-host-marker.blade.php` (the marker view it renders). Both belong to this group, but the group's file list did not name them. `AGENTS.md` has an unrelated uncommitted change, which I left alone.

**Conflict constraint.** The constraint is `<0.1.0`, as the salvage instructions require. Task 1.2's text says `<1.0.0` if Atrium is untagged. The PR has to record which constraint shipped.

**Left.** Task 1.6 (`vendor/bin/pest` all green) was not run, because the salvage instructions say the gate runs the suite. Only `pint --test` was run on the group's PHP files, and it passed. The CI lane is verified only by a workflow run.

## Group 1

**Files.** No code changed in this round. Tasks 1.1 to 1.5 were already committed in the salvaged round above (7ecf257). The salvaged commit contains every file the group needs: `composer.json`, `tests/ArchTest.php` (`only the Atrium layer uses Atrium`), `tests/TestCase.php`, `tests/Pest.php`, `tests/Feature/ComposerTest.php`, `tests/Feature/Ui/TestHostScreenTest.php`, the workbench `TestHostScreenController` with its `test-host` and `test-host-marker` views, `phpunit.xml.dist`, `phpunit.without-atrium.xml`, `tests/WithoutAtrium/` and the `without-atrium` job in `.github/workflows/tests.yml`. This round ticks task 1.6 only.

**Tests.** Task 1.6 is ticked without running the suite here: the job's instructions leave `vendor/bin/pest` to the gate after the build.

**Deviations.**
- The `conflict` constraint is `"refactor-circus/atrium": "<0.1.0"` (as are the `suggest` text and Polycart's matching job). Criterion 12 and the spec's Risks say that while Atrium has no release tag, the constraint must be `<1.0.0`, recorded in the PR. Atrium has no tags today, local or remote. Atrium's CHANGELOG already has a `v0.1.0` section from before the screen-extensions registry, so `<0.1.0` would not refuse that release if it were ever tagged. The constraint was left as committed and raised for a decision, because changing it moves the minimum version across the suite. `tests/Feature/ComposerTest.php` only asserts a non-empty constraint starting with `<`, so it passes with either value.
- Unchanged from the salvaged round: `tests/Feature/Ui/TestHostScreenTest.php` and `workbench/resources/views/test-host-marker.blade.php` sit outside the group's file list. `AGENTS.md` has an unrelated uncommitted change, still left alone.

## Group 2

**Files.** Committed on `ring-master/2026-10-29-2038-register-showroom-run-link--g2`.
- `config/impex.php`: `atrium.run_link.screens` (default `['showroom.transfers' => 'row.actions']`) with a banner. The banner covers the slot forms, the visibility rules, `[]` to switch the link off, the missing-key fallback and the malformed-entry rule.
- `src/Atrium/ScreenRegistrations.php` (final, static `register()`): returns early without `ScreenExtensions`. It reads the map, and a `null` or missing key uses the shipped default, read with `require` from the package's own `config/impex.php` so no Showroom name appears in `src/`. Each entry is validated (non-empty string key and slot) and registered inside its own `try`. An `InvalidArgumentException` from Impex or Atrium is thrown in `local` and `testing`, and anywhere else it is passed to `report()` and the entry is skipped. A config value that is not an array counts as one malformed entry. The authorize closure checks, in order: `RunModel` and `exists`, the `impex` key in `Atrium::pluginRegistry()->authorized($request)` (which Atrium memoizes per request), `ScreenAccess::allows('view', $record, [], $request)`, and `Route::has('atrium.impex.runs.show')`.
- `src/ImpexServiceProvider.php`: a private `registerAtriumScreens()` right after `registerAtriumPlugin()`, behind the same string `class_exists('RefactorCircus\Atrium\Atrium')` and `impex.ui.enabled === true` guard. `loadTranslationsFrom()` now runs before both, because the section label `__('impex::impex.run')` is translated at registration.
- `resources/views/ui/screens/run-link.blade.php`: one anchor, `underline-offset-2 hover:underline`, `data-testid="impex-run-link"`.
- Tests: `tests/Feature/Ui/ScreenRegistrationsTest.php`, `RunLinkVisibilityTest.php`, `RunLinkSectionTest.php` (including the exact-anchor escaping render test), the run-link case added to `StylesTest.php`, `tests/Feature/NoShowroomReferenceTest.php`, and `tests/PennantPlus/RunLinkPennantTest.php`.

**Outside the listed files.** `lang/en/impex.php` gains `open_run` ("Open run") and `open_run_flow` ("Open run :flow") for the link text and its accessible name.

**Tests.** Task 2.5 is ticked without running the suite, because the job's instructions leave `vendor/bin/pest` to the gate. Only `pint --test` (passed) and `php -l` were run on the changed files. Tests that need some care:
- Testbench never flushes `scoped()` bindings between requests in one test, so Atrium's `ScreenVisibility` would carry one request's answers into the next. The helpers that render the host more than once call `app()->forgetScopedInstances()` first, as each request in an app starts fresh.
- The "feature turned off in config" case (criterion 6) registers `Atrium::resolveFeaturesUsing()` that reads `test-features.{name}` from config, sets `impex.atrium.features` to `['impex-ui']`, and then sets that feature to `false`. Atrium has no config-driven feature switch of its own, and without PennantPlus every feature is on. The "plugin not authorized for the request" case uses a per-user resolver.
- The route-absent case rebuilds the router's `RouteCollection` without `atrium.impex.runs.show`.

**Deviations.**
- **Link text.** The spec says the link text is `{{ $record->flow }}` (criterion 4, Design). Task 2.4 as assigned, and Showroom's D-023, say the section renders "Open run", because Showroom keeps the plain flow name in its own cell. The view follows the task: the link reads "Open run" and carries the escaped flow name in `aria-label="Open run {flow}"`. Without that label, every row's link would read the same to a screen reader. Criterion 6's "flow name escaped" assertions are made against the label. Criterion 4's text should be updated to match.
- **Atrium class name.** The spec names the guard class `RefactorCircus\Atrium\ScreenExtensions`. In Atrium the registry is `RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions`, and that is the class guarded and read in the tests.
- **Unknown slot.** Atrium accepts any slot name matching its name pattern, and the spec's validation is "non-empty string slot", so "an unknown slot" is tested as a name Atrium rejects (`Not A Slot!`). A well-formed slot that the target screen does not declare registers and never renders, as for any section.
- **Query count.** With authorization on and a non-operator viewer, the bundled `RunPolicy::view` runs one `owners()->exists()` query per run unless the host has eager-loaded `owners`. The constant-query tests therefore cover three cases: authorization off, an operator, and an owner whose rows were loaded with `owners`. A separate test asserts that the policy check leaves the rows' relations unloaded. The query cost belongs to the policy, not to the section.

## Group 3 (salvaged)

**Found.** No index lock and no merge or rebase in progress in `Impex--2026-10-29-2038-register-showroom-run-link--g3`. The first build agent left uncommitted work that covers tasks 3.1 to 3.5:
- `src/Atrium/Screens.php` (`Screens::declare()`: `Screen::standard()`, `record()`, and the criterion 8 access closure through `ScreenAccess::allows($permission, $record ?? $subject, [], $request)`).
- All 10 screens declared and resolved in the five controllers after their own authorization, with `resolved` passed to the views. The flow and channel `row.actions` rows come from one `whereIn` query keyed by slug or name, with unsaved stand-ins for the rest. The channel page uses `$stored`, or else a stand-in.
- The 10 views render `screen-tabs` and `screen-tab-panel`. `header.actions` comes after the page's own actions, `before` and `after` go around a `data-testid="impex-content"` wrapper, and the other slots sit as follows:
  - `aside`: a right-hand column, rendered only when it has visible sections.
  - `row.actions`: in each row's last cell.
  - All three `<x-atrium::audit-trail>` calls are removed.
- `historyNavItem()` is dropped from `ImpexPlugin::navigation()`, and `tests/Feature/Ui/AuditTrailTest.php` is deleted (staged).
- `HistoryNavigationTest`'s `links its own audit log from its sidebar group` is replaced by `links no audit log from its sidebar group`.
- New tests:
  - `DeclaredScreensTest`, with a dataset over the 10 screens.
  - `ScreenAccessClosureTest`.
  - `ScreenLayoutTest`.
  - `NoHistoryUiTest`.
- Docs updated: CLAUDE.md (through `AGENTS.md`), README.md, `docs/11-dashboard.md` (a new "Screen extensions" section, and History comes from Keen) and the Boost skill.

**Checked.** A grep of `resources`, `src`, `docs` (outside `docs/agent/specs`), `README.md` and `AGENTS.md` finds no `audit-trail` or `historyNavItem`. `FlowOverrideModel::slug` and `ChannelModel::name` are fillable, so the stand-ins need no `forceFill`. The Atrium APIs used are all present in the vendored Atrium: `ResolvedScreen::openRegisteredTab`, `sections()`, `rowSections()`, and the `screen-sections`, `screen-tabs` and `screen-tab-panel` components. No other test declares the same global helper function names. `pint --test` passes on the group's PHP files.

**Committed.** All of the above, in fc681a3 on `ring-master/2026-10-29-2038-register-showroom-run-link--g3`. `CLAUDE.md` is a symlink to `AGENTS.md`, which is not on the group's file list. Only the Atrium-screens hunk of `AGENTS.md` was committed. Its other uncommitted hunk (a "Keep this checkout on `main`" line in the Ring Master block) is the unrelated change Group 1 noted, and it was left unstaged.

**Left.** Task 3.6 (`vendor/bin/pest` all green) was not run, because the salvage instructions leave the suite to the gate. The tests were not run in this round, so they may need fixes once the gate runs them. The most likely spots are the DOM region checks in `DeclaredScreensTest`, which use PHP 8.4's `Dom\HTMLDocument` and find the header from the `#atrium-main h1` ancestor, and the `?tab=` replacement assertions.

## Group 3

**Files.** No new changes in this round. The group's work is the salvaged commit fc681a3 on `ring-master/2026-10-29-2038-register-showroom-run-link--g3`, which covers tasks 3.1 to 3.5 (see "Group 3 (salvaged)" above). The branch differs from the job branch only in the 27 files of that commit, all on the group's file list except the Atrium-screens hunk of `AGENTS.md` (`CLAUDE.md` is a symlink to it).

**Tests.** Task 3.6 is ticked without running `vendor/bin/pest`, because the job's instructions leave the suite to the gate. The tests have not been run yet in any round. If the gate fails, look first at the spots named in the salvaged section: the `Dom\HTMLDocument` region checks in `DeclaredScreensTest` and the `?tab=` replacement assertions.

**Deviations.** None beyond those recorded in the salvaged section. The uncommitted "Keep this checkout on `main`" line in `AGENTS.md` is still unstaged. It is not part of this group.
