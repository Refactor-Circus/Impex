# Impex v2: API and MCP only

## Goal

Impex 2.0.0 ships only its JSON API and MCP server. The Atrium plugin goes; Atrium v2 hosts the UI. To support it, Impex adds the three read endpoints the Blade UI used to compute in PHP, a committed OpenAPI document and a committed, generated TypeScript client (`@refactor-circus/impex-client`). Atrium's Nuxt layer calls Impex through that client.

## Acceptance criteria

### New API

1. `GET impex/stats` (`impex.stats.show`) answers 200 with `{"data": {"runs": {"by_status": {...}, "active": int, "recent_failures": [...]}, "messages": {"window_hours": 24, "inbound": int, "outbound": int}}}`. `by_status` has one key for every `RunStatus` value, with zero for any status that has no runs.
2. In `ShowRunStatsAction`, `active` is the count of runs matched by `RunModel::scopeActive()`. `recent_failures` holds at most 5 failed runs, newest `finished_at` first, each serialized by `RunResource`. `inbound` and `outbound` count messages in each `Direction` whose `occurred_at` falls within the last 24 hours.
3. Stats authorization and scoping:
   - The endpoint authorizes `viewAny` on `RunModel`. With `impex.authorization` on, a guest gets 401 and a user the policy refuses gets 403.
   - With authorization on, every count and the failure list cover only the runs the caller owns, and the message counts cover only those runs' messages.
   - `messages` is `null` when the caller may not `viewAny` `MessageModel`.
   - With authorization off, nothing is scoped.
4. `GET impex/search?q=` (`impex.search.index`) answers 200 with a `RunResource` collection (`{"data": [...]}`, no pagination). It returns runs whose `flow` contains `q` or whose `id` starts with `q` (compared in lowercase), newest `created_at` first, then by `id`. At most `limit` runs are returned (1–25, default 5). In `q`, the `%`, `_` and `\` characters match literally.
5. Search validation, authorization and scoping:
   - `q` is required. It is trimmed, then must be a string of 1–255 characters.
   - An out-of-range or non-integer `limit` gets 422.
   - The endpoint authorizes `viewAny` on `RunModel`, and the guest and refusal answers match criterion 3 (401 and 403).
   - With authorization on, only the caller's own runs are searched.
6. `GET impex/settings-summary` (`impex.settings-summary.show`) answers 200 with `{"data": {...}}`, built from config with a cast and a hard default on every read. Only these keys are sent:
   - `retention`: `completed_runs_days`, `failed_runs_days`, `messages_days`, `artifacts_days`, `events_days` and `deliveries_days`, all integers.
   - `limits`: `max_step_seconds`, `resume_margin_seconds`, `lease_seconds`, `lock_seconds`, `max_resumptions`, `fan_out_max` and `sync_seconds`, all integers.
   - `artifacts`: `disk` (string or null), `path` (string), `inline_threshold` (int) and `memo_bytes` (int).
   - `preview_bytes`: an integer.

   A key a host adds under those config sections is not sent.
7. The settings summary authorizes `viewAny` on `RunModel`, with the same 401 and 403 answers as criterion 3.
8. Each new endpoint follows the package's patterns:
   - The controller method only returns `$request->persist()`.
   - The Request authorizes through the policy and validates with its Data's `rules()`.
   - `persist()` calls one action (`ShowRunStatsAction`, `SearchRunsAction`, `ShowSettingsSummaryAction`). Each action dispatches a starting event and a finished event.
   - Every payload goes through a final resource.
   - `tests/ArchTest.php` passes, including the parity rule. The three actions are listed in `MCP_EXCEPTIONS` (`tests/Pest.php`), each with its reason.
9. The new routes are declared in `src/Domains/Run/routes.php` and loaded by `loadApiRoutesFrom()`.
   - They exist only while `impex.routes.enabled` is `true`, which stays the shipped default.
   - Their prefix and middleware come from `impex.routes.prefix` and `impex.routes.middleware`.

### Atrium removal

10. These files are gone: `src/Atrium/**`, `resources/views/**` and `lang/**`. `ImpexServiceProvider` no longer does any of the following:
    - call `registerAtriumPlugin()` or `ScreenRegistrations::register()`
    - call `loadViewsFrom()` or `loadTranslationsFrom()`
    - define `Blade::if('impexCan')`
    - publish the `impex-views` or `impex-lang` tags

    No route named `atrium.impex.*` is registered.
11. No file under `src/` or `config/` contains the word "atrium" in any letter case. `tests/ArchTest.php` asserts that `RefactorCircus\Impex` does not use `RefactorCircus\Atrium`, with no `ignoring()`.
12. Config and classes:
    - `config/impex.php` has no `ui` key and no `atrium` key.
    - `ImpexSupportFeature` no longer exists.
    - The `impex.cortex.*` and `impex.routes.*` keys are unchanged.
13. `composer.json`, checked by `tests/Feature/ComposerTest.php`:
    - `refactor-circus/atrium` is absent from `require`, `require-dev`, `suggest` and `conflict`, and there is no `extra.atrium`.
    - `refactor-circus/pennantplus` is absent from `require-dev` and `suggest`.
    - `refactor-circus/keystone` is the only `refactor-circus/*` package in `require`.
    - There is no `test:without-atrium` script, and `composer test` does not call one.
    - `dedoc/scramble` is in `require-dev`.
14. Atrium-only tests and tooling are gone:
    - `tests/Feature/Ui/`, `tests/PennantPlus/`, `tests/PennantPlusTestCase.php` and `tests/WithoutAtrium/`
    - `phpunit.without-atrium.xml`
    - `HistoryNavigationTest` and `NavigationSectionTest`
    - the Atrium-only fixtures and the workbench test-host screen
    - the CI `without-atrium` job

    The remaining API, MCP and engine tests pass unchanged in behavior, and `composer test` passes.

### OpenAPI and client

15. `openapi.json` is committed at the package root.
    - It is an OpenAPI 3.1 document exported by `dedoc/scramble` from the Testbench workbench.
    - Every route named `impex.*` that is served under `impex.routes.prefix` has an operation in it, the three new routes included. Each operation's `operationId` is the route name.
16. In `openapi.json`, the success response of each of these operations is an object schema whose properties include the fields that operation's resource declares in `schema()`:
    - the new ones: `impex.stats.show`, `impex.search.index` and `impex.settings-summary.show`
    - the existing ones: `impex.runs.index`, `impex.runs.show`, `impex.messages.index`, `impex.flows.index`, `impex.channels.index` and `impex.subscriptions.index`

    A test checks this for each operation listed.
17. `client/` is committed and contains:
    - `package.json`: name `@refactor-circus/impex-client`, version `2.0.0`, `type: module`. `openapi-fetch` is a dependency. `openapi-typescript` and `typescript` are dev dependencies at exact versions. The scripts are `generate` and `typecheck`.
    - `package-lock.json` and `tsconfig.json`.
    - `src/schema.d.ts`, generated by `openapi-typescript` from `../openapi.json`.
    - `src/index.ts`, which exports `createImpexClient(options)`. It takes `baseUrl` plus `openapi-fetch`'s `ClientOptions` and returns `Client<paths>`. The file also re-exports the `paths`, `components` and `operations` types.
    - `README.md`.

    `npm --prefix client run typecheck` passes.
18. `composer client:generate` regenerates everything in this order:
    - `openapi.json`, from `scramble:export` through `vendor/bin/testbench`
    - `client/src/schema.d.ts`, after `npm ci` and `npm run generate` in `client/`
    - `client/openapi.sha256`, the sha256 of `openapi.json`
19. Staleness is caught in two places:
    - A Pest test fails when a fresh export differs from the committed `openapi.json` (line endings normalized), or when `client/openapi.sha256` does not match the committed `openapi.json`.
    - A CI `client` job runs `composer client:generate`, then `git diff --exit-code -- openapi.json client`, then the client typecheck.
20. Scramble is a development tool only. Nothing under `src/` or `config/` references `Dedoc\Scramble`, and its configuration and any extensions live in `workbench/`.

### Docs

21. `README.md` describes Impex as JSON API plus MCP and lists the three new endpoints. It covers `openapi.json`, the `client/` package and `composer client:generate`, and names Atrium v2 as the optional UI. No instructions for installing or configuring Atrium remain.
22. `CHANGELOG.md` has a `## [v2.0.0]` section with an upgrade note. The note says:
    - the Atrium UI was removed; install Atrium v2, whose Impex integration replaces it
    - the removed keys are `impex.ui.enabled`, `impex.atrium.features`, `impex.atrium.show_all` and `impex.atrium.run_link.screens`, and they move to Atrium's `atrium.integrations.impex.*`
    - `ImpexSupportFeature` was removed
    - the `impex-views` and `impex-lang` publish tags were removed
    - operator mode has no API equivalent yet (see D-006)

    It also lists the new endpoints and the client.
23. The following describe the package as API plus MCP, with no Atrium layer, and name `composer client:generate`:
    - the Boost skill (`resources/boost/skills/impex-development/SKILL.md`)
    - `AGENTS.md`, which `CLAUDE.md` links to
    - `docs/09-api.md`, which also documents the three endpoints
    - `docs/11-dashboard.md`, now a pointer to Atrium v2
    - `docs/13-configuration.md`, `docs/01-installation.md`, `docs/08-ownership.md` and `docs/README.md`

    `tests/Feature/DocsTest.php` asserts these strings.

## Design

### New endpoints (Run domain)

| Method | URI | Name | Controller | Request | Action | Response |
| --- | --- | --- | --- | --- | --- | --- |
| GET | `impex/stats` | `impex.stats.show` | `RunStatsController::show` | `ShowRunStatsRequest` | `ShowRunStatsAction` | `RunStatsResource` |
| GET | `impex/search` | `impex.search.index` | `RunSearchController::index` | `IndexRunSearchRequest` | `SearchRunsAction` (`SearchRunsData`) | `RunResource::collection` |
| GET | `impex/settings-summary` | `impex.settings-summary.show` | `SettingsSummaryController::show` | `ShowSettingsSummaryRequest` | `ShowSettingsSummaryAction` | `SettingsSummaryResource` |

These three files live under `src/Domains/Run/`:

- `Actions/ShowRunStatsAction.php`: `execute(?Model $viewer, bool $withMessages): RunStats`. It runs one grouped status count (the query `ImpexPlugin::statusCounts()` used), the `active()` count, the 5 latest failures, and two message counts over `occurred_at >= now()->subDay()`, scoped like `ImpexPlugin::ownedMessages()`. It dispatches `RunStatsShowingActionEvent` and `RunStatsShownActionEvent`. There is no transaction, because it only reads.
- `Actions/SearchRunsAction.php`: `execute(SearchRunsData $data, ?Model $viewer): Collection<int, RunModel>`. Its events are `RunsSearchingActionEvent` and `RunsSearchedActionEvent`. `SearchRunsData` has `q` (`required|string|min:1|max:255`; the Request trims it in `prepareForValidation()`) and `limit` (`integer|min:1|max:25`, default 5).
- `Actions/ShowSettingsSummaryAction.php`: `execute(): SettingsSummary`. It reads the whitelisted keys with a cast and a hard default each. Its events are `SettingsSummaryShowingActionEvent` and `SettingsSummaryShownActionEvent`.

`RunStats` and `SettingsSummary` are readonly value objects in `Domains/Run/Data/`. They are named for what they hold and are not action input. The resources are `Domains/Run/Http/Resources/RunStatsResource.php` and `SettingsSummaryResource.php`; both are `final`, extend Keystone's `JsonResource` and declare `schema()`. `RunStatsResource` nests `RunResource` for `recent_failures`.

The Requests pass the actor (`$this->actor()`) as viewer, as `IndexRunsRequest` does. `ShowRunStatsRequest` passes `withMessages: $this->allows('viewAny', MessageModel::class)`. `RunModel` is the domain's own model; reading `MessageModel` is allowed by the dependencies standard, which permits reading another domain's models.

All three actions go in `MCP_EXCEPTIONS` (D-005), so the parity arch test passes. MCP agents already have `list-runs-tool`.

### Removal

- Delete `src/Atrium/`, `resources/views/`, `lang/`, `tests/Feature/Ui/`, `tests/PennantPlus/`, `tests/PennantPlusTestCase.php`, `tests/WithoutAtrium/`, `phpunit.without-atrium.xml`, `tests/Feature/HistoryNavigationTest.php`, `tests/Feature/NavigationSectionTest.php` and `tests/Fixtures/Features/`. Also delete the workbench test host: `workbench/app/Http/Controllers/TestHostScreenController.php`, `workbench/resources/views/test-host*.blade.php` and `TestCase::defineRoutes()`.
- In `ImpexServiceProvider`, drop the Atrium imports, `registerAtriumPlugin()`, `registerAtriumScreens()`, `loadViewsFrom()`, `loadTranslationsFrom()`, `Blade::if('impexCan')`, and the `impex-views` and `impex-lang` publishes. `loadHistoryRoutes()`, `registerCortex()` and the rest stay.
- In `config/impex.php`, drop the `ui` and `atrium` sections and the `ImpexSupportFeature` import. Reword the `FlowOverridePolicy` docblock so it no longer names Atrium.
- In `tests/`:
  - `TestCase` stops registering `AtriumServiceProvider` and setting `atrium.disabled`.
  - `tests/Pest.php` drops `ResetsScreenExtensions` and the PennantPlus suite.
  - `phpunit.xml.dist` drops the PennantPlus suite and the WithoutAtrium comment.
  - `CortexTestCase` adds whatever providers Cortex itself needs, Atrium's included if Cortex requires it. That is the only place a test may name Atrium.
  - `tests/Feature/ApiTest.php` loses "mounts the dashboard through atrium", and `NoHistoryUiTest` is rewritten without Atrium.
- In `composer.json`, drop:
  - `refactor-circus/atrium` from `require-dev`, `suggest` and `conflict`
  - `refactor-circus/pennantplus` from `require-dev` and `suggest`, and its VCS repository
  - `extra.atrium`
  - the `test:without-atrium` script and its entry in `test`

  The Atrium VCS repository stays (D-007). The `refactor-circus/cortex` dev dependency and its suggestion stay.
- In `testbench.yaml`, drop the Atrium provider, `atrium-assets`, the Pennant migrations and the `start: '/atrium'` entry; `start` becomes `/docs/api`.
- In `workbench/app/Providers/WorkbenchServiceProvider.php`, drop the `viewAtrium` gate, `impex.atrium.show_all` and any Pennant setup.
- In `.github/workflows/tests.yml`, remove the `without-atrium` job.

### OpenAPI

- `dedoc/scramble` goes in `require-dev`. The workbench configures it: `scramble.api_path` is `impex`, `info.version` is `2.0.0`, and every operation id comes from the route name, through Scramble's operation-id resolver or a workbench operation transformer.
- If Scramble cannot infer response bodies through `$request->persist()`, add a workbench-only operation extension, `workbench/app/OpenApi/ResourceResponses.php`. It maps route name to resource class and writes each resource's `schema()` as the success response schema; JSON Schema is valid OpenAPI 3.1. That covers criterion 16. A test asserts that every route criterion 16 lists is in the map.
- Export with `@php vendor/bin/testbench scramble:export --path=openapi.json`.
- `tests/Feature/OpenApiTest.php` runs the same export into a temp file, using `Process`, so it matches the composer script exactly. It compares the result with `openapi.json` after normalizing line endings. It also asserts the operation ids of criterion 15, the response properties of criterion 16, and `client/openapi.sha256`.

### TypeScript client

```
client/
  package.json        @refactor-circus/impex-client 2.0.0; scripts: generate, typecheck
  package-lock.json
  tsconfig.json       strict, noEmit
  README.md
  openapi.sha256      sha256 of ../openapi.json the schema was generated from
  src/schema.d.ts     openapi-typescript ../openapi.json -o src/schema.d.ts
  src/index.ts        createImpexClient(options) => createClient<paths>(options); type re-exports
```

The client ships TypeScript source; there is no build step and no `dist` (D-008). `"exports": {".": "./src/index.ts"}`. `client/node_modules` goes in `.gitignore`. The composer script is:

```json
"client:generate": [
    "@php vendor/bin/testbench scramble:export --path=openapi.json",
    "npm --prefix client ci",
    "npm --prefix client run generate",
    "@php -r \"file_put_contents('client/openapi.sha256', hash_file('sha256', 'openapi.json').PHP_EOL);\""
]
```

CI adds a `client` job on Ubuntu with PHP 8.5 and Node LTS. It runs `composer update`, then `composer client:generate`, then `git diff --exit-code -- openapi.json client`, then `npm --prefix client run typecheck`.

### Config

`impex.routes` keeps its keys and its `enabled => true` default (D-001). Its banner gains one line: the operator API is described by `openapi.json` and the `@refactor-circus/impex-client` package. The `ui` and `atrium` sections are removed. Nothing is added.

## Out of scope

- Atrium's Impex integration: the pages, widgets, search source, settings panel, nav badge and run link, plus the `atrium.integrations.impex.*` keys. All of it belongs to Atrium's v2 spec.
- The `impex.cortex.*` MCP registration with Cortex and the `refactor-circus/cortex` dev dependency. They are flagged as a remaining cross-package reference and get their own spec if the suite rule is applied to them (D-004).
- MCP tools for stats, search and settings summary (D-005).
- An API replacement for operator mode, `impex.atrium.show_all` (D-006).
- Publishing the client to an npm registry. Atrium pins it as a git dependency on the v2.0.0 tag.
- Tagging the v2.0.0 release.
- Keystone's removal of `registerAtriumPlugin()`, which is a separate spec this one waits for.

## Risks and open questions

- **Scramble inference through `persist()`.** Controllers return `JsonResponse` from `$request->persist()`, so Scramble may emit empty response schemas. The workbench `ResourceResponses` extension is the fallback. Criterion 16 covers only the listed operations; other operations may stay loosely typed until a follow-up.
- **Scramble on Laravel 13 / PHP 8.5.** If no release supports this stack, the group stops and returns a decision rather than pinning a dev branch.
- **Determinism of the export.** The staleness test needs byte-stable output. Paths are sorted by Scramble, and the version and server URL are fixed in workbench config. A Windows runner may differ only in line endings, which the test normalizes.
- **Atrium still installed in development.** `refactor-circus/cortex` requires Atrium, so Atrium stays in `vendor/` through Cortex. The arch test (criterion 11), not uninstalling Atrium, proves `src/` does not use it. The authoritative no-Atrium CI lane is gone, because nothing in `src/` can load Atrium.
- **Operators lose the all-runs view.** Under `impex.authorization`, lists, stats and search cover the caller's own runs only. A host that needs operators can turn authorization off on an operator-only route stack, or wait for a follow-up policy ability (D-006).
- **Git dependency on a subfolder.** npm cannot install a subdirectory of a git repository; pnpm (`#v2.0.0&path:/client`) and Bun can. Atrium's layer chooses how to consume it. The client's README notes this.
- **Message count cost.** The 24-hour message counts filter on `occurred_at`. If that column has no suitable index on large ledgers, a follow-up adds a migration. This spec adds none.
