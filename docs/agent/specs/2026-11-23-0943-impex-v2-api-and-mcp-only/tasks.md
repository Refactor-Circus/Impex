# Tasks: Impex v2: API and MCP only

The groups run in order. Groups 1–3 add the new API while the Atrium UI still exists, so the suite stays green. Group 4 then removes the UI and its tests in one pass. Groups 5–6 add OpenAPI and the client against the final route set, and group 7 writes the docs.

## Group 1: Stats endpoint
depends on: none
files: src/Domains/Run/Actions/ShowRunStatsAction.php, src/Domains/Run/Data/RunStats.php, src/Domains/Run/Events/RunStatsShowingActionEvent.php, src/Domains/Run/Events/RunStatsShownActionEvent.php, src/Domains/Run/Http/Requests/ShowRunStatsRequest.php, src/Domains/Run/Http/Controllers/RunStatsController.php, src/Domains/Run/Http/Resources/RunStatsResource.php, src/Domains/Run/routes.php, tests/Pest.php, tests/Feature/Actions/ShowRunStatsActionTest.php, tests/Feature/Api/RunStatsApiTest.php
criteria: 1, 2, 3, 8, 9

- [x] 1.1 Write action tests in `tests/Feature/Actions/ShowRunStatsActionTest.php`: every `RunStatus` key is present and zero-filled; `active` counts the runs `scopeActive()` matches; `recent_failures` returns at most 5 failed runs, newest `finished_at` first; inbound and outbound counts include only messages from the last 24 hours; a viewer sees only owned runs and those runs' messages; `withMessages: false` returns null messages; the starting and finished events are dispatched.
- [x] 1.2 Write API wiring tests in `tests/Feature/Api/RunStatsApiTest.php`, addressing routes by name `impex.stats.show`: 200 with the payload shape of criterion 1; with authorization on, a guest gets 401 and a refusing `RunPolicy` (a test fixture policy) gets 403; `messages` is null when a fixture policy refuses `viewAny` on messages; the route is absent when `impex.routes.enabled` is false.
- [x] 1.3 Implement `RunStats` (readonly value object), the event pair, `ShowRunStatsAction`, `RunStatsResource` (final, with `schema()`, nesting `RunResource`), `ShowRunStatsRequest` (`viewAny` on `RunModel`, actor as viewer, `withMessages` from `allows('viewAny', MessageModel::class)`), `RunStatsController::show` returning `$request->persist()`, and `Route::get('stats', ...)->name('stats.show')` in `Domains/Run/routes.php`.
- [x] 1.4 Add `ShowRunStatsAction` to `MCP_EXCEPTIONS` in `tests/Pest.php` with the reason from D-005.
- [x] 1.5 Run vendor/bin/pest; all green

## Group 2: Search endpoint
depends on: 1
files: src/Domains/Run/Actions/SearchRunsAction.php, src/Domains/Run/Data/SearchRunsData.php, src/Domains/Run/Events/RunsSearchingActionEvent.php, src/Domains/Run/Events/RunsSearchedActionEvent.php, src/Domains/Run/Http/Requests/IndexRunSearchRequest.php, src/Domains/Run/Http/Controllers/RunSearchController.php, src/Domains/Run/routes.php, tests/Pest.php, tests/Feature/Actions/SearchRunsActionTest.php, tests/Feature/Api/RunSearchApiTest.php
criteria: 4, 5, 8, 9

- [ ] 2.1 Write action tests in `tests/Feature/Actions/SearchRunsActionTest.php`: `flow` contains `q`; `id` prefix matches, including from an uppercase `q`; `%` and `_` in `q` match literally; results are newest first with ties broken by id; `limit` caps the results (default 5); a viewer sees only owned runs; the event pair is dispatched.
- [ ] 2.2 Write API wiring tests in `tests/Feature/Api/RunSearchApiTest.php` (route `impex.search.index`): 200 with a `RunResource` collection; 422 for a missing, blank (after trim) or 256-character `q`, and for `limit` 0, 26 or `abc`; with authorization on, a guest gets 401 and a refusing policy gets 403.
- [ ] 2.3 Implement `SearchRunsData` (`q`, `limit = 5`, `rules()`), the event pair, `SearchRunsAction` (escape LIKE wildcards, lowercase `q` for the id prefix), `IndexRunSearchRequest` (trim `q` in `prepareForValidation()`, `viewAny` on `RunModel`, `SearchRunsData::fromValidated()`), `RunSearchController::index`, and `Route::get('search', ...)->name('search.index')`.
- [ ] 2.4 Add `SearchRunsAction` to `MCP_EXCEPTIONS` in `tests/Pest.php`.
- [ ] 2.5 Run vendor/bin/pest; all green

## Group 3: Settings summary endpoint
depends on: 2
files: src/Domains/Run/Actions/ShowSettingsSummaryAction.php, src/Domains/Run/Data/SettingsSummary.php, src/Domains/Run/Events/SettingsSummaryShowingActionEvent.php, src/Domains/Run/Events/SettingsSummaryShownActionEvent.php, src/Domains/Run/Http/Requests/ShowSettingsSummaryRequest.php, src/Domains/Run/Http/Controllers/SettingsSummaryController.php, src/Domains/Run/Http/Resources/SettingsSummaryResource.php, src/Domains/Run/routes.php, tests/Pest.php, tests/Feature/Actions/ShowSettingsSummaryActionTest.php, tests/Feature/Api/SettingsSummaryApiTest.php
criteria: 6, 7, 8, 9

- [ ] 3.1 Write action tests in `tests/Feature/Actions/ShowSettingsSummaryActionTest.php`: values come from config with integer casts; a missing key falls back to its hard default; a host-added key under `retention`, `limits` or `artifacts` is not included; `artifacts.disk` is null when unset; the event pair is dispatched.
- [ ] 3.2 Write API wiring tests in `tests/Feature/Api/SettingsSummaryApiTest.php` (route `impex.settings-summary.show`): 200 with exactly the keys of criterion 6; with authorization on, a guest gets 401 and a refusing policy gets 403.
- [ ] 3.3 Implement `SettingsSummary`, the event pair, `ShowSettingsSummaryAction`, `SettingsSummaryResource` (final, with `schema()`), `ShowSettingsSummaryRequest`, `SettingsSummaryController::show`, and `Route::get('settings-summary', ...)->name('settings-summary.show')`.
- [ ] 3.4 Add `ShowSettingsSummaryAction` to `MCP_EXCEPTIONS` in `tests/Pest.php`.
- [ ] 3.5 Run vendor/bin/pest; all green

## Group 4: Remove the Atrium UI, its config, composer entries and tests
depends on: 3
files: src/Atrium/** (delete), resources/views/** (delete), lang/** (delete), src/ImpexServiceProvider.php, src/Domains/Flow/Policies/FlowOverridePolicy.php, config/impex.php, composer.json, testbench.yaml, phpunit.xml.dist, phpunit.without-atrium.xml (delete), .github/workflows/tests.yml, workbench/app/Providers/WorkbenchServiceProvider.php, workbench/app/Http/Controllers/TestHostScreenController.php (delete), workbench/resources/views/test-host.blade.php (delete), workbench/resources/views/test-host-marker.blade.php (delete), tests/ArchTest.php, tests/Pest.php, tests/TestCase.php, tests/CortexTestCase.php, tests/PennantPlusTestCase.php (delete), tests/PennantPlus/** (delete), tests/WithoutAtrium/** (delete), tests/Feature/Ui/** (delete), tests/Fixtures/Features/** (delete), tests/Feature/HistoryNavigationTest.php (delete), tests/Feature/NavigationSectionTest.php (delete), tests/Feature/NoHistoryUiTest.php, tests/Feature/ApiTest.php, tests/Feature/ComposerTest.php, tests/Feature/AtriumRemovedTest.php
criteria: 10, 11, 12, 13, 14

- [ ] 4.1 Write tests first. New `tests/Feature/AtriumRemovedTest.php`: no route name starts with `atrium.impex.`; `src/Atrium`, `resources/views` and `lang` do not exist; no file under `src/` or `config/` contains "atrium" (case-insensitive); `config('impex.ui')` and `config('impex.atrium')` are null, and the `impex.cortex.*` keys are unchanged; no `impex-views` or `impex-lang` publish group is registered; Rewrite `tests/Feature/ComposerTest.php` for criterion 13; In `tests/ArchTest.php`, change the Atrium rule to `->not->toUse('RefactorCircus\Atrium')` with no `ignoring()`.
- [ ] 4.2 Delete the UI and its tests in one pass: Delete `src/Atrium`, `resources/views` and `lang`; Delete the Atrium-only tests, fixtures and test cases: `tests/Feature/Ui`, `tests/PennantPlus`, `tests/PennantPlusTestCase.php`, `tests/WithoutAtrium`, `tests/Fixtures/Features`, `HistoryNavigationTest` and `NavigationSectionTest`; Delete `phpunit.without-atrium.xml` and the workbench test-host controller and views; Remove "mounts the dashboard through atrium" from `ApiTest`, and rewrite `NoHistoryUiTest` without Atrium (or delete it if nothing non-Atrium is left to assert).
- [ ] 4.3 Strip the service provider: Remove from `ImpexServiceProvider`: the Atrium imports, `registerAtriumPlugin()`, `registerAtriumScreens()`, `loadViewsFrom()`, `loadTranslationsFrom()`, `Blade::if('impexCan')`, and the `impex-views` and `impex-lang` publishes; Reword the `FlowOverridePolicy` docblock so it no longer names Atrium.
- [ ] 4.4 Edit `config/impex.php`: remove the `ui` and `atrium` sections and the `ImpexSupportFeature` import, and keep `routes`, `mcp` and `cortex` as they are.
- [ ] 4.5 Edit `composer.json`: Remove `refactor-circus/atrium` and `refactor-circus/pennantplus` from `require-dev` and `suggest`; Remove `conflict.refactor-circus/atrium`, `extra.atrium`, the PennantPlus VCS repository, the `test:without-atrium` script and `@test:without-atrium` in `test`; Keep the Atrium VCS repository (D-007).
- [ ] 4.6 Update the tooling: `testbench.yaml`: drop the Atrium provider, `atrium-assets` and the Pennant migrations, and change `start` to `/`; `phpunit.xml.dist`: drop the PennantPlus suite and the WithoutAtrium comment; `.github/workflows/tests.yml`: remove the `without-atrium` job; `WorkbenchServiceProvider`: drop the `viewAtrium` gate, `show_all` and the Pennant setup; `tests/TestCase.php`: drop `AtriumServiceProvider`, `atrium.disabled` and `defineRoutes()`; `tests/Pest.php`: drop `ResetsScreenExtensions` and the PennantPlus `uses()`; `tests/CortexTestCase.php`: register whatever providers Cortex needs, Atrium's included only if Cortex fails without it.
- [ ] 4.7 Run `composer update` in the worktree, then vendor/bin/pest and `composer test`; all green

## Group 5: OpenAPI export with Scramble
depends on: 4
files: composer.json, workbench/app/Providers/WorkbenchServiceProvider.php, workbench/app/OpenApi/ResourceResponses.php, testbench.yaml, config/impex.php, openapi.json, tests/Feature/OpenApiTest.php
criteria: 15, 16, 20

- [ ] 5.1 Write `tests/Feature/OpenApiTest.php` with these tests: Export through `Process` (`php vendor/bin/testbench scramble:export --path=<tmp>`) and compare the result with the committed `openapi.json`, with line endings normalized; every route named `impex.*` under `impex.routes.prefix` has an operation whose `operationId` is the route name; For each operation in criterion 16, the success response schema's properties include the resource's `schema()` keys; Nothing under `src/` or `config/` contains `Dedoc\Scramble`.
- [ ] 5.2 Add `dedoc/scramble` to `require-dev`, choosing a release that supports Laravel 13 and PHP 8.5. If none exists, stop and return a decision.
- [ ] 5.3 Configure Scramble in `WorkbenchServiceProvider`: `api_path` `impex`, `info.version` `2.0.0`, and a fixed server URL. Use the route name as the operation id.
- [ ] 5.4 Fall back to `workbench/app/OpenApi/ResourceResponses.php` only if Scramble leaves response schemas empty. It maps route name to resource class and writes `schema()` as the success response. Register it in the workbench.
- [ ] 5.5 Add the line about `openapi.json` and the client package to the `impex.routes` config banner. Add a `composer openapi:generate` helper script, set `testbench.yaml` `start` to `/docs/api`, run the export and commit `openapi.json`.
- [ ] 5.6 Run vendor/bin/pest; all green

## Group 6: TypeScript client
depends on: 5
files: client/package.json, client/package-lock.json, client/tsconfig.json, client/README.md, client/src/index.ts, client/src/schema.d.ts, client/openapi.sha256, composer.json, .gitignore, .github/workflows/tests.yml, tests/Feature/ClientTest.php
criteria: 17, 18, 19

- [ ] 6.1 Write `tests/Feature/ClientTest.php`: `client/package.json` has name `@refactor-circus/impex-client`, version `2.0.0`, `openapi-fetch` in `dependencies`, and exact versions (no `^` or `~`) for `openapi-typescript` and `typescript`; `client/package.json` has the `generate` and `typecheck` scripts; `client/src/index.ts` exports `createImpexClient` and the `paths`, `components` and `operations` types; `client/openapi.sha256` equals the sha256 of `openapi.json`; `composer.json` `client:generate` runs the four steps of criterion 18 in order.
- [ ] 6.2 Create `client/`: `package.json` (`type: module`, `exports: ./src/index.ts`), `tsconfig.json` (strict, `noEmit`), `src/index.ts` (the `createImpexClient(options)` wrapper over `createClient<paths>`), and `README.md`. The README covers usage, consuming the client as a git dependency (pnpm `path:/client`), and regeneration.
- [ ] 6.3 Add the `client:generate` composer script and `/client/node_modules` to `.gitignore`; leave `.gitattributes` alone (client/ and openapi.json stay in the git tree Atrium pins).
- [ ] 6.4 Run `composer client:generate`. Commit `package-lock.json`, `src/schema.d.ts` and `openapi.sha256`, then run `npm --prefix client run typecheck`.
- [ ] 6.5 Add a `client` job to `.github/workflows/tests.yml`: PHP 8.5, Node LTS, `composer update`, `composer client:generate`, `git diff --exit-code -- openapi.json client`, then the typecheck.
- [ ] 6.6 Run vendor/bin/pest; all green

## Group 7: Docs for v2.0.0
depends on: 4, 5, 6
files: README.md, CHANGELOG.md, AGENTS.md, resources/boost/skills/impex-development/SKILL.md, docs/README.md, docs/01-installation.md, docs/08-ownership.md, docs/09-api.md, docs/11-dashboard.md, docs/13-configuration.md, tests/Feature/DocsTest.php
criteria: 21, 22, 23

- [ ] 7.1 Rewrite `tests/Feature/DocsTest.php` to assert: README: mentions `openapi.json`, `@refactor-circus/impex-client`, `client:generate`, `impex/stats`, `impex/search`, `impex/settings-summary` and Atrium v2. It does not contain `composer require refactor-circus/atrium`, `impex.atrium.` or `without-atrium`: CHANGELOG: has `## [v2.0.0]` and names `impex.ui.enabled`, `impex.atrium.show_all`, `impex.atrium.run_link.screens`, `ImpexSupportFeature`, `impex-views`, `impex-lang` and `atrium.integrations.impex`; Boost skill and `AGENTS.md`: mention `client:generate` and contain no `src/Atrium`; `docs/09-api.md`: documents the three endpoints.
- [ ] 7.2 Rewrite README: drop the Atrium dashboard, run link, operator and no-Atrium sections. Add the three endpoints, "OpenAPI and TypeScript client", and "UI: Atrium v2 (optional)".
- [ ] 7.3 Add the CHANGELOG `## [v2.0.0]` section with the upgrade note from criterion 22 (including D-006 on operator mode), Added (the endpoints and the client) and Removed.
- [ ] 7.4 Update the Boost skill. Update `AGENTS.md` (which `CLAUDE.md` links to): rewrite the Layout paragraph, drop the "Atrium screens" bullets and their `registerAtriumPlugin()` and `XData::from` notes, move the bullets that are not about Atrium (Events, Tables, HTTP requests, `ImpexException`, Policy) under Keystone, and add the client regeneration command.
- [ ] 7.5 Update `docs/09-api.md` (the endpoints and the OpenAPI/client section), rewrite `docs/11-dashboard.md` as a pointer to Atrium v2, and remove the Atrium keys and steps from `docs/13-configuration.md`, `docs/01-installation.md`, `docs/08-ownership.md` and `docs/README.md`.
- [ ] 7.6 Run vendor/bin/pest and `composer test`; all green
