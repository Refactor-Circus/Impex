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
