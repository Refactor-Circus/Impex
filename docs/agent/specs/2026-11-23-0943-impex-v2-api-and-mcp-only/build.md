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
