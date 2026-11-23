# Build

## Group 1

Stats endpoint: `GET impex/stats` (`impex.stats.show`). Criteria 1, 2, 3, 8, 9.

### Files

- `src/Domains/Run/Data/RunStats.php`: `final readonly` value object with `byStatus`, `active`, `recentFailures` (a `RunModel` collection) and `messages` (`array{window_hours, inbound, outbound}|null`).
- `src/Domains/Run/Events/RunStatsShowingActionEvent.php` (viewer, withMessages) and `RunStatsShownActionEvent.php` (stats, viewer).
- `src/Domains/Run/Actions/ShowRunStatsAction.php`: `execute(?Model $viewer, bool $withMessages): RunStats`. Runs one grouped status count, the `active()` count, the 5 latest failures (`finished_at` desc, then `id` desc), and two message counts over the last 24 hours. Each query is scoped to the viewer's owned runs, and messages through `whereHas('run', ...)`. There is no transaction.
- `src/Domains/Run/Http/Resources/RunStatsResource.php`: final, has `schema()`, and nests `RunResource::collection()` for `recent_failures`.
- `src/Domains/Run/Http/Requests/ShowRunStatsRequest.php`: authorizes `viewAny` on `RunModel`, passes `actor()` as viewer and `allows('viewAny', MessageModel::class)` as `withMessages`.
- `src/Domains/Run/Http/Controllers/RunStatsController.php`: `show()` returns `$request->persist()`.
- `src/Domains/Run/routes.php`: `Route::get('stats', ...)->name('stats.show')`.
- `tests/Pest.php`: `ShowRunStatsAction` is in `MCP_EXCEPTIONS` with the D-005 reason.
- `tests/Feature/Actions/ShowRunStatsActionTest.php`, `tests/Feature/Api/RunStatsApiTest.php`.
- Outside the group's list:
  - `tests/Fixtures/Policies/RefusingRunPolicy.php` and `RefusingMessagePolicy.php`: the fixture policies the API tests need, which the test-layers standard places in `tests/Fixtures/`.
  - `tests/Feature/EventsTest.php`: the asserted action count goes from 35 to 36. Groups 2 and 3 must raise it again.

### Tests

- Action tests cover these cases:
  - every status is present and zero-filled
  - `active` matches `RunModel::query()->active()`
  - at most 5 failures, newest first, with a completed run excluded
  - the 24-hour window for both directions
  - viewer scoping of runs and messages, plus no scoping without a viewer
  - `withMessages: false` gives null messages
  - the event pair
- API tests cover these cases:
  - 200 with the exact payload keys
  - 401 for a guest and 403 under `RefusingRunPolicy`
  - owner scoping with authorization on
  - `messages: null` under `RefusingMessagePolicy`
  - the route uses the configured prefix and middleware
  - the route is absent when `impex.routes.enabled` is false
- A targeted run of these two files plus `EventsTest.php` and `ArchTest.php` passed: 39 tests. `pint --test` and `composer analyse` pass. The gate runs the full suite.

### Deviations

- The actions-events standard says read actions dispatch no events. Criterion 8 and the package's existing read actions (`ShowRunAction`, `ListRunsAction`) require the pair, so the action dispatches them. It also follows the package's `execute()`/`perform()` shape rather than a Keystone `Action` base with `handle()`, as every Impex action does today.

## Group 2

Search endpoint: `GET impex/search` (`impex.search.index`). Criteria 4, 5, 8, 9.

### Files

- `src/Domains/Run/Data/SearchRunsData.php`: `final readonly`, with `q` (`required|string|min:1|max:255`) and `limit = 5` (`sometimes|integer|min:1|max:25`), and `rules()`.
- `src/Domains/Run/Events/RunsSearchingActionEvent.php` (data, viewer) and `RunsSearchedActionEvent.php` (runs, viewer).
- `src/Domains/Run/Actions/SearchRunsAction.php`: `execute(SearchRunsData $data, ?Model $viewer = null): Collection<int, RunModel>`. It matches `flow like %q%` or `id like lower(q)%`. Both use `escape '!'`, with `!`, `%` and `_` escaped in `q`. That makes `%`, `_` and `\` literal on SQLite, MySQL and PostgreSQL. A backslash escape character is not portable in a MySQL string literal. Results are scoped by `whereOwnedBy($viewer)` when there is a viewer, ordered `created_at` desc then `id` desc, and limited to `limit`. There is no transaction.
- `src/Domains/Run/Http/Requests/IndexRunSearchRequest.php`: trims a string `q` in `prepareForValidation()`, authorizes `viewAny` on `RunModel`, uses `SearchRunsData::rules()`, and builds with `SearchRunsData::fromValidated()`. It passes `actor()` as the viewer and answers `RunResource::collection($runs)->response()`, which gives `{"data": [...]}` with no pagination.
- `src/Domains/Run/Http/Controllers/RunSearchController.php`: `index()` returns `$request->persist()`.
- `src/Domains/Run/routes.php`: `Route::get('search', ...)->name('search.index')`.
- `tests/Pest.php`: `SearchRunsAction` is in `MCP_EXCEPTIONS` with the D-005 reason.
- `tests/Feature/Actions/SearchRunsActionTest.php` and `tests/Feature/Api/RunSearchApiTest.php`.
- Outside the group's list: in `tests/Feature/EventsTest.php`, the asserted action count goes from 36 to 37, as group 1 noted. Group 3 must raise it to 38.

### Tests

- Action tests cover these cases:
  - `flow` contains `q`
  - the `id` prefix matches, from both a lowercase and an uppercase `q`, and a mid-id match does not count
  - `%`, `_` and `\` match literally, and a lone `%` or `_` does not widen the match
  - newest first, with ties broken by id
  - the default limit of 5, and limits of 2 and 25
  - viewer scoping, and no scoping without a viewer
  - the event pair carries the Data, the result and the viewer
- API tests cover these cases:
  - 200 with a `RunResource` collection, newest first, and only a `data` key
  - `q` is trimmed and `limit` applies
  - 422 for a missing, blank (after trim) or 256-character `q`; 255 characters are accepted
  - 422 for `limit` 0, 26 or `abc`
  - 401 for a guest and 403 under `RefusingRunPolicy`
  - owner scoping with authorization on
  - the route uses the configured prefix and middleware, and is absent when `impex.routes.enabled` is false
- A targeted run of these two files plus `ArchTest.php`, `EventsTest.php` and `tests/Modes/RenamedTables` passed. `pint --test` and `phpstan` on `src/Domains/Run` pass. The gate runs the full suite.

### Deviations

- As in group 1, the read action dispatches the event pair (criterion 8) and uses the package's `execute()`/`perform()` shape.
- `whereRaw()` uses the plain column names `flow` and `id` rather than grammar-wrapped ones, because PHPStan requires a literal SQL string. Neither name is reserved in the supported databases.

## Group 3

Settings summary endpoint: `GET impex/settings-summary` (`impex.settings-summary.show`). Criteria 6, 7, 8, 9.

### Files

- `src/Domains/Run/Data/SettingsSummary.php`: a `final readonly` value object with these properties:
  - `retention`, `limits` and `artifacts`, each an array with a fixed shape
  - `previewBytes` (int)
- `src/Domains/Run/Events/SettingsSummaryShowingActionEvent.php` carries nothing, because the action takes no input. `SettingsSummaryShownActionEvent.php` carries the summary.
- `src/Domains/Run/Actions/ShowSettingsSummaryAction.php`: `execute(): SettingsSummary`.
  - It reads each whitelisted key on its own, with `(int) (config($key) ?? $default)`. The hard defaults match `config/impex.php`.
  - `artifacts.path` is cast to string, with the default `impex`.
  - `artifacts.disk` is null when it is unset or an empty string.
  - `preview_bytes` comes from `impex.messages.preview_bytes`.
  - Keys are read one by one and never as whole sections, so a key a host adds is never sent. There is no transaction.
- `src/Domains/Run/Http/Resources/SettingsSummaryResource.php`: final, with `schema()`. Every field is `required()`, and `artifacts.disk` is `nullable()`.
- `src/Domains/Run/Http/Requests/ShowSettingsSummaryRequest.php`: authorizes `viewAny` on `RunModel`. `persist()` calls the action and answers with the resource.
- `src/Domains/Run/Http/Controllers/SettingsSummaryController.php`: `show()` returns `$request->persist()`.
- `src/Domains/Run/routes.php`: `Route::get('settings-summary', ...)->name('settings-summary.show')`.
- `tests/Pest.php`: `ShowSettingsSummaryAction` is in `MCP_EXCEPTIONS` with the D-005 reason.
- `tests/Feature/Actions/ShowSettingsSummaryActionTest.php` and `tests/Feature/Api/SettingsSummaryApiTest.php`.
- Outside the group's list: in `tests/Feature/EventsTest.php`, the asserted action count goes from 37 to 38, as group 2 noted.

### Tests

- Action tests cover these cases:
  - string config values are cast to integers
  - every key falls back to its hard default when its section is empty
  - keys a host adds under `retention`, `limits` and `artifacts` are left out
  - `artifacts.disk` is null when it is null or empty
  - the event pair is dispatched, and the finished event carries the result
- API tests cover these cases:
  - 200 with exactly the keys of criterion 6, all integers where required, and host-added keys absent
  - 401 for a guest and 403 under `RefusingRunPolicy`
  - 200 for an allowed signed-in user
  - the route uses the configured prefix and middleware, and is absent when `impex.routes.enabled` is false
- A targeted run of these two files plus `EventsTest.php` and `ArchTest.php` passed: 36 tests. `pint --test` on the changed files and `phpstan` on `src/Domains/Run` pass. The gate runs the full suite.

### Deviations

- As in groups 1 and 2, the read action dispatches the event pair (criterion 8) and uses the package's `execute()`/`perform()` shape. Its starting event carries no input because the action takes none.
- `SettingsSummary` takes its state through a readonly constructor, like `RunStats`, rather than the value-objects standard's `public private(set)` properties with fluent setters. The spec calls for a readonly value object.

## Group 4

Remove the Atrium UI, its config, composer entries and tests. Criteria 10, 11, 12, 13, 14.

### Files

- Deleted:
  - `src/Atrium/**`, `resources/views/**` and `lang/**`
  - `tests/Feature/Ui/**`, `tests/PennantPlus/**`, `tests/PennantPlusTestCase.php`, `tests/WithoutAtrium/**` and `tests/Fixtures/Features/**`
  - `tests/Feature/HistoryNavigationTest.php` and `tests/Feature/NavigationSectionTest.php`
  - `phpunit.without-atrium.xml`
  - `workbench/app/Http/Controllers/TestHostScreenController.php` and `workbench/resources/views/test-host.blade.php` and `test-host-marker.blade.php`
- `src/ImpexServiceProvider.php`: dropped the Atrium imports, the `Blade` import, `loadTranslationsFrom()`, `registerAtriumPlugin()`, `registerAtriumScreens()` (the call and the method), `loadViewsFrom()`, `Blade::if('impexCan')`, and the `impex-views` and `impex-lang` publishes. `registerCortex()`, `registerPolicies()`, `registerNotFoundResponses()`, `registerMcpServer()`, `loadHistoryRoutes()`, the config and migration publishes and the prune command stay.
- `src/Domains/Flow/Policies/FlowOverridePolicy.php`: the docblock says changes are denied by default, and that an application points `impex.policies` at its own policy to allow them.
- `config/impex.php`: dropped the `ui` and `atrium` sections and the `ImpexSupportFeature` import. The `audit` banner named "its Atrium pages", so it now names only the history route and MCP tool. `routes`, `mcp` and `cortex` are unchanged.
- `composer.json`:
  - Dropped `refactor-circus/atrium` from `require-dev`, `suggest` and `conflict`.
  - Dropped `refactor-circus/pennantplus` from `require-dev` and `suggest`, along with its VCS repository.
  - Dropped `extra.atrium`, the `test:without-atrium` script and its `test` entry.
  - Added `dedoc/scramble` `^0.13.55` to `require-dev`; see Deviations.
  - The Atrium VCS repository stays (D-007).
- `testbench.yaml`: dropped the Atrium provider, the Pennant migrations and `atrium-assets`. `start` is `/`; group 5 sets `/docs/api`.
- `phpunit.xml.dist`: dropped the PennantPlus suite and the WithoutAtrium comment.
- `.github/workflows/tests.yml`: removed the `without-atrium` job.
- `workbench/app/Providers/WorkbenchServiceProvider.php`: dropped the `viewAtrium` gate, `impex.atrium.show_all`, the Pennant store setup and the unused `Gate` import. Two comments that described dashboard buttons and the Channels page now describe the API.
- `tests/TestCase.php`: dropped `AtriumServiceProvider`, `atrium.disabled`, `defineRoutes()` and the test-host import.
- `tests/Pest.php`: dropped `ResetsScreenExtensions`, the PennantPlus `uses()` and a stale dashboard comment.
- `tests/CortexTestCase.php`: unchanged. Cortex v0.1.0 boots with `AiServiceProvider` and `CortexServiceProvider` alone, because it guards its Atrium code with `class_exists()`, so it does not need Atrium's provider.
- `tests/ArchTest.php`: the rule is now `arch('nothing uses Atrium')->expect('RefactorCircus\Impex')->not->toUse('RefactorCircus\Atrium')`, with no `ignoring()`.
- `tests/Feature/ApiTest.php`: removed "mounts the dashboard through atrium".
- `tests/Feature/NoHistoryUiTest.php`: kept the audit-trail and `historyNavItem` scan, with its docblock reworded to drop Atrium. Dropped the test that `docs/11-dashboard.md` mentions Keen, because group 7 rewrites that doc as a pointer to Atrium v2.
- `tests/Feature/ComposerTest.php`: rewritten for criterion 13.
- New `tests/Feature/AtriumRemovedTest.php`.

### Tests

- `AtriumRemovedTest` covers these cases:
  - no route name starts with `atrium.impex.`
  - `src/Atrium`, `resources/views` and `lang` are not directories
  - no file under `src/` or `config/` contains "atrium" in any letter case
  - `impex.ui` and `impex.atrium` are null, and `impex.cortex` and `impex.routes` equal their shipped values
  - `ImpexSupportFeature` does not exist
  - `ServiceProvider::publishableGroups()` has `impex-config` but neither `impex-views` nor `impex-lang`
- `ComposerTest` covers these cases:
  - Keystone is the only suite package in `require`
  - Atrium is absent from `require`, `require-dev`, `suggest` and `conflict`, and there is no `extra.atrium`
  - PennantPlus is absent from `require-dev` and `suggest`
  - Showroom is never a dependency
  - there is no `test:without-atrium` script, and `test` does not call one
  - `dedoc/scramble` is in `require-dev` and not in `require`
- `composer update` ran. It removed `refactor-circus/atrium`, `refactor-circus/pennantplus` and `laravel/pennant`, and installed `dedoc/scramble` v0.13.55. It also moved `refactor-circus/keystone` and `refactor-circus/cortex` from the previously installed dev-main to the tagged `v0.1.1` and `v0.1.0` that `^0.1` resolves to.
- `composer analyse` passes, and Pint passes on the changed files. A targeted run of `AtriumRemovedTest`, `ComposerTest`, `NoHistoryUiTest`, `ArchTest` and `tests/Cortex` passed: 46 tests. Following the prompt, the full suite and `composer test` were left to the gate.

### Deviations

- `dedoc/scramble` went into `require-dev` here rather than in group 5 (task 5.2). Criterion 13, which this group proves, requires it in `ComposerTest`, and the assertion would fail until group 5 without it. v0.13.55 requires `illuminate/contracts ^10|^11|^12|^13` and `php ^8.1`, so it supports Laravel 13 and PHP 8.5. In tests it is never loaded: Testbench ignores package discovery outside the workbench. Group 5 only needs to configure it.
- Spec risk note: `refactor-circus/cortex` lists Atrium only in `require-dev`, not `require`. Atrium is therefore no longer installed in development at all, and the Atrium VCS repository kept under D-007 now resolves nothing.

## Group 5

OpenAPI export with Scramble. Criteria 15, 16, 20.

### Files

- `composer.json`: `dedoc/scramble` `^0.13.55` was already in `require-dev` from group 4 (installed v0.13.55, which supports Laravel 13 and PHP 8.5). This group adds the `openapi:generate` script, `@php vendor/bin/testbench scramble:export --path=openapi.json`.
- `workbench/app/Providers/WorkbenchServiceProvider.php`:
  - `register()` sets `scramble.api_path` to `impex`, `info.version` to `2.0.0`, a description, `ui.title` `Impex`, the fixed server `http://localhost/impex`, and `scramble.middleware` `['web']`, so `/docs/api` opens in any environment.
  - While `scramble:export` runs, `register()` also points `database.default` at an empty in-memory SQLite connection. Scramble reads model columns whenever their tables exist, so without this a workbench migrated by `composer build` would export a different document from CI.
  - `boot()` documents only routes named `impex.*`. It leaves out `impex.channels.receive.{channel}`, which the workbench's `legacy-erp` channel config adds; the package does not ship it. Every operation id is set to the route name, and `ResourceResponses` is registered as an operation transformer.
- `workbench/app/OpenApi/ResourceResponses.php`: a Scramble `OperationExtension`. `responses(JsonSchema)` maps each criterion 16 route name to its success body, built from the resource's `schema()`:
  - single resources use `{data: schema()}`
  - plain lists (search, channels) use `{data: [schema()]}`
  - cursor-paginated lists (runs, messages, subscriptions) use `collectionSchema()` with their Data's `pagination()`, which adds `meta`
  - flows has no resource class, so it uses `ListFlowsTool::outputSchema()`, the same payload

  `handle()` replaces the operation's 2xx response with that body as raw JSON Schema (valid OpenAPI 3.1) and keeps the error responses. Scramble left `impex.flows.index` and `impex.channels.index` untyped (`data: string`), so the fallback was needed. It covers all nine routes so that the document states what `schema()` promises.
- `config/impex.php`: the `routes` banner says the operator API is described by `openapi.json` and the `@refactor-circus/impex-client` package.
- `testbench.yaml`: `start` is `/docs/api`.
- `openapi.json`: exported with `composer openapi:generate`. It is OpenAPI 3.1.2 with 53 operations.
- `tests/Feature/OpenApiTest.php`: new.

### Tests

`OpenApiTest` covers these cases:

- the document is OpenAPI 3.1 at version `2.0.0`
- a fresh export through `Process` (`PHP_BINARY vendor/bin/testbench scramble:export --path=<tmp>`, run from the package root) equals the committed `openapi.json`, with line endings normalized and trailing whitespace trimmed
- every route in the test app named `impex.*` under `impex.routes.prefix` has an operation whose `operationId` is the route name, the three new routes included
- for each of the nine criterion 16 operations, the 200 response is an object whose `data` (or each `data` item) is an object whose properties include every key of the resource's `schema()`. For flows, the keys come from `ListFlowsTool::outputSchema()`.
- `ResourceResponses::responses()` maps exactly the nine criterion 16 routes
- no file under `src/` or `config/` contains `Dedoc\Scramble`

A targeted run of `tests/Feature/OpenApiTest.php` passed: 14 tests. `pint --test` passed on the changed PHP files. The gate runs the full suite.

### Deviations

- `impex.flows.index` has no resource class, because `IndexFlowsRequest` answers a plain array. Its documented response and its criterion 16 check use `ListFlowsTool::outputSchema()`, which declares the same payload. Adding a `FlowResource` in `src/` was outside this group's files.
- The `client/openapi.sha256` check that the spec's design puts in `OpenApiTest` is left to group 6, whose task 6.1 places it in `ClientTest`.
- `impex.history.index` comes from Keystone and still shows a loosely typed (`string`) response. Criterion 16 does not list it.

## Group 6

TypeScript client. Criteria 17, 18, 19.

### Files

- `client/package.json`: `@refactor-circus/impex-client` `2.0.0`, `private`, `type: module`, `exports: {".": "./src/index.ts"}` (D-008: source only, no `dist`). The scripts are `generate` (`openapi-typescript ../openapi.json -o src/schema.d.ts`) and `typecheck` (`tsc --noEmit`). `openapi-fetch` `^0.17.0` is the runtime dependency. `openapi-typescript` `7.13.0` and `typescript` `5.9.3` are exact dev pins. TypeScript 7 is out, but `openapi-typescript` 7.13.0 declares `typescript: ^5.x` as a peer, so 5.9.3 is pinned.
- `client/package-lock.json`: created with `npm install --package-lock-only`, then used by `npm ci` in `client:generate`.
- `client/tsconfig.json`: `strict`, `noEmit`, `moduleResolution: Bundler`, `verbatimModuleSyntax`, `noUncheckedIndexedAccess`, `include: ["src"]`.
- `client/src/index.ts`: `createImpexClient(options: ImpexClientOptions): Client<paths>`, where `ImpexClientOptions = ClientOptions & { baseUrl: string }`, wraps `createClient<paths>(options)`. It also exports the `ImpexClient` alias and re-exports the `components`, `operations` and `paths` types.
- `client/src/schema.d.ts`: generated by `composer client:generate`.
- `client/openapi.sha256`: the sha256 of `openapi.json` with a trailing newline, written by `client:generate`.
- `client/README.md`: covers usage, installing from git with pnpm or Bun (`github:refactor-circus/impex#v2.0.0&path:/client`, since npm cannot install a subfolder), regeneration and its four steps, the typecheck, and where staleness is caught.
- `composer.json`: adds the `client:generate` script exactly as the spec's design gives it. `openapi:generate` from group 5 stays.
- `.gitignore`: adds `/client/node_modules`. `.gitattributes` is untouched.
- `.github/workflows/tests.yml`: adds the `client` job on `ubuntu-latest` with PHP 8.5 (`setup-php`, same pin as the matrix) and Node `lts/*` (`actions/setup-node`, pinned to the v7.1.0 commit). It runs `composer update`, `composer client:generate`, `git diff --exit-code -- openapi.json client` and `npm --prefix client run typecheck`.
- `tests/Feature/ClientTest.php`: new.

### Tests

`ClientTest` covers these cases:

- `client/package.json` has the name, the version and `type: module`
- `openapi-fetch` is in `dependencies`
- `openapi-typescript` and `typescript` are pinned to exact `x.y.z` versions (dataset)
- the `generate` and `typecheck` scripts exist
- `package-lock.json`, `tsconfig.json`, `src/schema.d.ts` and `README.md` exist (dataset)
- `src/index.ts` exports the function `createImpexClient` and re-exports the `paths`, `components` and `operations` types from `./schema`
- `client/openapi.sha256` equals `hash_file('sha256', 'openapi.json')`
- `composer.json` `client:generate` is exactly the four steps of criterion 18, in order

`composer client:generate` ran cleanly. Scramble printed its usual "table does not exist" warnings for model schemas, and `openapi.json` came out byte-identical to the committed copy. `npm --prefix client run typecheck` passed. A targeted run of `tests/Feature/ClientTest.php` passed: 12 tests. `pint --test` passed on it. The gate runs the full suite.

### Deviations

- None from the spec.
