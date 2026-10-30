# Tasks: Register Showroom run link

## Group 1: Composer, arch rule, test host and the no-Atrium proof
package: Impex
depends on: none
files: composer.json, tests/ArchTest.php, tests/TestCase.php, tests/Pest.php, tests/Feature/ComposerTest.php, workbench/app/Http/Controllers/TestHostScreenController.php, workbench/resources/views/test-host.blade.php, phpunit.xml.dist, phpunit.without-atrium.xml, tests/WithoutAtrium/, .github/workflows/tests.yml
criteria: 5, 11, 12, 13, 14

- [x] 1.1 Write tests: ComposerTest (require only keystone among refactor-circus, atrium in suggest with registry minimum and Keen, atrium in require-dev, atrium conflict starting with `<`, no showroom anywhere), the WithoutAtrium assertions of criterion 14, and a test-host smoke test that the list screen renders its rows with the standard slots.
- [x] 1.2 Move refactor-circus/atrium from require to require-dev (dev-main), add its suggest description, and add a conflict on Atrium older than the screen-registry release (exactly `<0.1.0`, D-020).
- [x] 1.3 Add the arch rule `only the Atrium layer uses Atrium` to tests/ArchTest.php.
- [x] 1.4 Build the workbench TestHostScreenController and test-host view (screen-tabs, screen-tab-panel, header.actions, before, after and per-row row.actions sections) and wire it plus ResetsScreenExtensions into tests/TestCase.php and tests/Pest.php.
- [x] 1.5 Add phpunit.without-atrium.xml, tests/WithoutAtrium/bootstrap.php (removes the Atrium PSR-4 prefix before any Atrium class loads) and its test case (explicit providers, no Atrium discovery, no workbench Atrium code), exclude it from phpunit.xml.dist, add the `test:without-atrium` script called by `composer test` after `@test:unit`, and add the `without-atrium` CI job (remove Atrium and dependents, `composer update`).
- [x] 1.6 Run vendor/bin/pest; all green

## Group 2: Run link on other packages' screens
package: Impex
depends on: 1
files: config/impex.php, src/Atrium/ScreenRegistrations.php, src/ImpexServiceProvider.php, resources/views/ui/screens/run-link.blade.php, tests/Feature/Ui/ScreenRegistrationsTest.php, tests/Feature/Ui/RunLinkSectionTest.php, tests/Feature/Ui/RunLinkVisibilityTest.php, tests/Feature/Ui/StylesTest.php, tests/Feature/NoShowroomReferenceTest.php, tests/PennantPlus/RunLinkPennantTest.php
criteria: 1, 2, 3, 4, 6

- [x] 2.1 Write tests: ScreenRegistrationsTest (default, missing-key fallback, explicit empty, custom list, ui disabled, malformed entries throw in testing, production reports one and registers the valid entry), RunLinkVisibilityTest (each condition false, constant queries over about 25 rows), RunLinkSectionTest per criterion 6 with the Pennant variant in tests/PennantPlus, the exact-anchor escaping render test, StylesTest coverage and the src grep for Showroom names.
- [x] 2.2 Add `atrium.run_link.screens` with its banner and the `['showroom.transfers' => 'row.actions']` default to config/impex.php.
- [x] 2.3 Implement ScreenRegistrations::register() (per-entry validation and try, throw in local/testing, report and skip elsewhere, guarded on ScreenExtensions) and call it from a private registerAtriumScreens() in ImpexServiceProvider behind the registerAtriumPlugin() guard.
- [x] 2.4 Implement the authorize closure (RunModel and exists first, memoized plugin check, ScreenAccess view, Route::has) and the impex::ui.screens.run-link view rendering only the escaped flow-name anchor.
- [x] 2.5 Run vendor/bin/pest; all green

## Group 3: Declare Impex's own screens and remove history UI
package: Impex
depends on: 1
files: src/Atrium/Screens.php, src/Atrium/ImpexPlugin.php, src/Atrium/Http/Controllers/ChannelUiController.php, src/Atrium/Http/Controllers/FlowUiController.php, src/Atrium/Http/Controllers/MessageUiController.php, src/Atrium/Http/Controllers/RunUiController.php, src/Atrium/Http/Controllers/SubscriptionUiController.php, resources/views/ui/runs/index.blade.php, resources/views/ui/runs/show.blade.php, resources/views/ui/messages/index.blade.php, resources/views/ui/messages/show.blade.php, resources/views/ui/flows/index.blade.php, resources/views/ui/channels/index.blade.php, resources/views/ui/channels/create.blade.php, resources/views/ui/channels/show.blade.php, resources/views/ui/subscriptions/index.blade.php, resources/views/ui/subscriptions/show.blade.php, tests/Feature/Ui/DeclaredScreensTest.php, tests/Feature/Ui/ScreenAccessClosureTest.php, tests/Feature/Ui/ScreenLayoutTest.php, tests/Feature/Ui/AuditTrailTest.php, tests/Feature/HistoryNavigationTest.php, tests/Feature/NoHistoryUiTest.php, CLAUDE.md, README.md, docs/11-dashboard.md, resources/boost/skills/impex-development/SKILL.md
criteria: 7, 8, 9, 10, 11

- [x] 3.1 Write tests: DeclaredScreensTest dataset over the 10 screens (every standard slot, region-exclusive markers, undeclared slot throws, tab opens via ?tab= and replaces content, stand-in rows fire no events or queries), ScreenAccessClosureTest (owner, other user, operator, viewAny), ScreenLayoutTest (assertSeeInOrder markers, empty aside not rendered, zero registered by default) and NoHistoryUiTest (no atrium.history.show nav item, scoped grep, docs/11-dashboard.md mentions Keen).
- [x] 3.2 Add Screens::declare() with the criterion 8 access closure and declare and resolve each of the 10 screens in their controllers, with the flow and channel stand-ins built from one keyed query, passing `resolved` to the views.
- [x] 3.3 Render screen-tabs, screen-tab-panel and the standard slot sections in every Impex view per Atrium criterion 15, leaving markup unchanged when nothing is registered.
- [x] 3.4 Remove the audit-trail calls from ui/runs/index, ui/runs/show and ui/messages/show, drop historyNavItem() from ImpexPlugin::navigation(), delete tests/Feature/Ui/AuditTrailTest.php and replace `links its own audit log from its sidebar group` with the criterion 10 assertion.
- [x] 3.5 Remove audit-trail and historyNavItem text from CLAUDE.md, README.md, docs/11-dashboard.md and the Boost skill, saying History comes from Keen.
- [x] 3.6 Run vendor/bin/pest; all green

## Group 4: Docs and changelog
package: Impex
depends on: 2, 3
files: README.md, docs/11-dashboard.md, resources/boost/skills/impex-development/SKILL.md, CHANGELOG.md, tests/Feature/DocsTest.php
criteria: 15, 16

- [ ] 4.1 Write tests in DocsTest for the README (`run_link.screens`, `impex.run-link`, `suggest`) and CHANGELOG (`suggest`, `conflict`, `run_link.screens`, `Keen`, `Showroom`) strings.
- [ ] 4.2 Document `impex.atrium.run_link.screens`, the `impex.run-link` key, the visibility rules, the malformed-entry rule, Atrium as a suggestion and the CI lane as the authoritative no-Atrium proof in README.md, docs/11-dashboard.md and the Boost skill.
- [ ] 4.3 Add the CHANGELOG upgrade note covering every topic listed in criterion 15.
- [ ] 4.4 Run composer test (including test:without-atrium) and vendor/bin/pest; all green
