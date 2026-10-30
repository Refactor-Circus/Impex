Risk: high

## What changed
- **Run link:** new `src/Atrium/ScreenRegistrations.php` registers an `impex.run-link` section on each screen listed in `impex.atrium.run_link.screens` (default `showroom.transfers` => `row.actions`). The view is `resources/views/ui/screens/run-link.blade.php`.
- **Screen declarations:** all 10 Impex Atrium screens now use `Screen::standard()` through the new `src/Atrium/Screens.php`. The controllers and the `ui/**` views render tabs and every standard slot.
- **Atrium decoupling:** `<x-atrium::audit-trail>` and `historyNavItem()` are removed. In `composer.json`, Atrium moves to `require-dev` and `suggest`, and gains `conflict: <0.1.0`. `ImpexServiceProvider` registers the screens behind the Atrium and UI guards.
- **Tests and CI:** a workbench test-host screen, a `WithoutAtrium` suite (`composer test:without-atrium`), a `without-atrium` CI job, new arch rule, and docs/CHANGELOG/README/Boost skill updates.

## Why
Showroom drops its inline run link, so Impex has to own that link and place it on other packages' screens through config only. Impex names no host class. Atrium's history components are being removed (Keen now supplies History), so Impex stops depending on them and treats Atrium as optional. Decisions that shaped it:
- D-021: a bad config entry throws in local/testing and is reported and skipped in production.
- D-020, D-022, D-025, D-028: Impex is proven to run without Atrium, both in process and in CI, and an Atrium older than the registry is refused by `conflict`.
- D-023, D-026, D-029: the link is a separate `row.actions` fragment.
- D-024: one spec and one PR.
- D-031: the link reads "Open run" and carries the flow name in `aria-label`.

## Look at
- `src/Atrium/ScreenRegistrations.php:61` and `:119`: the per-entry register/fail logic, with the environment-dependent throw. Also the `showsRunLink` visibility chain: record type first, then plugin authorized, `ScreenAccess`, and route exists.
- `composer.json:48`: the new `conflict` on Atrium (`<0.1.0`), and the `require` to `require-dev` and `suggest` move. This one is a public dependency change.
- `config/impex.php`: the new `atrium.run_link.screens` key. An app that published the config before this change falls back to the shipped default only when the key is `null`.
- `src/Atrium/Screens.php` and `src/Atrium/Http/Controllers/*`: the access closure, and the unsaved stand-in rows for flows and channels.
- `.github/workflows/tests.yml` and `tests/WithoutAtrium/bootstrap.php`: the authoritative no-Atrium lane.

## Tested
The last gate (round-1) passed: phpstan 0 errors, pint passed, pest 476 passed, and the without-atrium suite 6 passed.
- Criteria 1-2: `ScreenRegistrationsTest` and `NoShowroomReferenceTest`.
- Criterion 3: `RunLinkVisibilityTest` (each condition false on its own, constant query count).
- Criteria 4-6: `RunLinkSectionTest`, `TestHostScreenTest`, `StylesTest` and `tests/PennantPlus/RunLinkPennantTest`.
- Criteria 7-9: `DeclaredScreensTest` (all 10 screens), `ScreenAccessClosureTest` and `ScreenLayoutTest`.
- Criterion 10: `NoHistoryUiTest` and `HistoryNavigationTest`.
- Criterion 12: `ComposerTest`.
- Criterion 13: `ArchTest`.
- Criterion 14: `tests/WithoutAtrium`. The CI lane itself is verified only by the workflow run.
- Criterion 15: `DocsTest`.
- `AuditTrailTest` is deleted, and `HistoryNavigationTest`, `tests/Pest.php` and `tests/TestCase.php` change, as criterion 11 expects.

## Risks
- Grouped merge: Atrium, Keen, Showroom and Impex must land together (D-024), or the run link or History disappears in between.
- Apps that relied on Impex pulling in Atrium must now require it. The `conflict` only works once Atrium's first tag, v0.1.0, includes the registry.
- Apps with Atrium but without Keen permanently lose History in Impex.
- In production, a malformed `run_link.screens` entry is skipped with one report, so that link stays missing until someone acts on the report.
- The CI `without-atrium` lane cannot be checked locally. It needs to be green on the PR.
- Nits: 1 (left open, non-blocking; see the round-1 reviews).
