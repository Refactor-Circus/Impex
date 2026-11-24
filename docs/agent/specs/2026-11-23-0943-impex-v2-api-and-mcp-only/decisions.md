# Decisions

## D-001: Does the API stay on by default (`impex.routes.enabled`)?
- Choice: Yes. `impex.routes.enabled` keeps its shipped default of `true`. The new routes load through the same `loadApiRoutesFrom()` group and are off whenever the host turns the API off.
- Rationale: The routes ship enabled today, so changing the default would silently break every existing API consumer in a release that is already breaking for another reason. Atrium v2 also needs the API on to render anything. The config banner already warns hosts to add auth middleware.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-002: What exactly does `GET impex/stats` return?
- Choice: `{"data": {"runs": {"by_status": {<every RunStatus>: int}, "active": int, "recent_failures": [RunResource x <=5]}, "messages": {"window_hours": 24, "inbound": int, "outbound": int} | null}}`. The payload has the same numbers the three widgets and the nav badge computed, with the same queries and the same owner scoping. `messages` is null when the caller may not `viewAny` messages.
- Rationale: It reproduces what the Blade UI showed, no more and no less. One endpoint keeps it to one request per dashboard load. Making `messages` nullable mirrors the per-widget authorization the plugin had, without adding a second endpoint.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-003: What exactly does `GET impex/search` accept and return?
- Choice:
  - `q` is required. It is trimmed and must be 1–255 characters.
  - `limit` is optional, 1–25, default 5.
  - The endpoint matches runs by `flow` containing `q` (LIKE wildcards escaped) or by `id` starting with the lowercased `q`.
  - Results are newest first, as a plain `RunResource` collection with no pagination.
  - Authorization is `viewAny` on runs, scoped to owned runs.
- Rationale: These are the Atrium search source's semantics (5 results, flow or id prefix). The only additions are input bounds and wildcard escaping, so a stray `%` cannot widen the match. Returning `RunResource` keeps one serializer per model. Atrium builds its own result rows and links.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-004: Do the flagged cross-package references (`impex.cortex.*`, the Cortex dev dependency) change here?
- Choice: No. `impex.cortex.*`, `registerCortex()`, the `refactor-circus/cortex` dev dependency, its suggestion and `tests/Cortex` stay as they are. If the suite decides they break the "no cross-package knowledge" rule, they get their own spec.
- Rationale: The shape puts them outside this spec, and Cortex integration goes through Keystone's guarded `CortexIntegration`. Touching it here would widen a breaking release with no user decision behind it.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-005: Do stats, search and settings summary get MCP tools (parity rule)?
- Choice: No. The three actions are listed in `MCP_EXCEPTIONS` in `tests/Pest.php`. The stated reason is that they are dashboard aggregates for the Atrium v2 integration, and agents use `list-runs-tool` and its filters.
- Rationale: Declared exceptions are the arch test's mechanism. The narrowest change that meets the shape (API only) avoids three new tools, schemas and tests in a breaking release. A follow-up can add tools if agents need them.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-006: What replaces operator mode (`impex.atrium.show_all`)?
- Choice: Nothing new in v2.0.0. The key is removed. The API keeps its current authorization:
  - With `impex.authorization` on, lists, stats and search cover the caller's own runs.
  - With it off, nothing is scoped.

  The CHANGELOG says so. Hosts that need operators turn authorization off on an operator-only route stack, or wait for a follow-up spec. That spec would add a policy ability, for example `viewAll`, that the list requests consult.
- Rationale: The shape says operator mode is "decided by the API's own authorization", which today means the policies and `impex.authorization`. Adding a new ability would change every list endpoint's scoping. That is an authorization design question the user has not settled, and the conservative default is to add no new access path.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-007: Does the Atrium VCS repository stay in `composer.json`?
- Choice: Yes. The `repositories` entry for Atrium stays, while every `refactor-circus/atrium` entry in `require-dev`, `suggest` and `conflict` and `extra.atrium` goes. The PennantPlus VCS repository goes with its dev dependency.
- Rationale: The `refactor-circus/cortex` dev dependency requires Atrium. Composer does not inherit a dependency's repositories, so the root needs the Atrium repository to resolve. The repository entry names no dependency, and `src/` stays free of Atrium.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-008: Does the client ship compiled JS or TypeScript source?
- Choice: TypeScript source only (`exports: ./src/index.ts`), with no build step and no committed `dist`. The wrapper is `createImpexClient(options)` over `openapi-fetch`'s `createClient<paths>`. Generator versions are pinned exactly in `client/package.json`, and `package-lock.json` is committed.
- Rationale: The one consumer is Atrium's Nuxt layer, which compiles TypeScript itself. A committed `dist` would be a third artifact to keep fresh. Exact pins keep the generated `schema.d.ts` byte-stable for the staleness check.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-009: How is staleness detected?
- Choice:
  - A Pest test (`tests/Feature/OpenApiTest.php`) re-exports through `vendor/bin/testbench scramble:export` into a temp file and compares it with `openapi.json`, normalizing line endings. It also checks that `client/openapi.sha256` matches `openapi.json`.
  - A CI `client` job runs `composer client:generate` and then `git diff --exit-code -- openapi.json client`, and type-checks the client.
- Rationale: The Pest test needs no Node, so `composer test` stays PHP-only on every matrix lane, Windows included. Node-dependent regeneration runs once in its own CI job.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-010: Where do the new endpoints live, and how are they authorized?
- Choice:
  - All three live in the Run domain: controllers, requests, actions, events, `RunStats` and `SettingsSummary` value objects, and resources in `Domains/Run/Http/Resources/`.
  - Each is authorized by `viewAny` on `RunModel`. Stats additionally asks `viewAny` on `MessageModel` for its `messages` block.
  - Settings summary exposes only a fixed whitelist of config keys.
- Rationale: The aggregates are about runs, and the settings are the engine's own limits and retention. Reading `MessageModel` from Run is allowed by the dependencies standard. A whitelist means a host's custom config keys are never exposed by accident.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-011: What happens to `lang/`, views and their publish tags?
- Choice: They are deleted. `lang/en/impex.php`, `resources/views/` and the `impex-lang` and `impex-views` publish tags all go, and the CHANGELOG lists the removal.
- Rationale: Every translation key and every view is used only by the Atrium layer. Nothing in the API or MCP calls `__('impex::...')`.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-012: Does the no-Atrium test suite and CI lane stay?
- Choice: No. Remove `tests/WithoutAtrium`, `phpunit.without-atrium.xml`, the `test:without-atrium` script and the CI `without-atrium` job. The arch test "`RefactorCircus\Impex` does not use `RefactorCircus\Atrium`", with no ignoring, takes their place.
- Rationale: The lane proved that the guarded Atrium layer was optional. With no Atrium code left in `src/`, there is nothing to guard, and the arch test checks the remaining invariant.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-013: Spec review
- Choice: Spec review was skipped. `meta.yml` is set to `status: approved` directly after write-spec and create-tasks.
- Rationale: The user asked for this job to proceed without stopping and without spec review.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-014: impex.flows.index has no resource class; is checking flows against ListFlowsTool::outputSchema() enough?
- Choice: Yes. Keep ListFlowsTool::outputSchema() as the source for flows in the workbench ResourceResponses and the OpenAPI test, as built. A FlowResource can come in a later spec.
- Rationale: Same payload, already declared; adding a resource would change src/ outside the group. Recommended option.
- Date: 2026-11-23
- Raised by: factory build (group 5); default chosen because the user asked to proceed without stopping

## D-015: Which version does the suite release under?
- Choice: Every suite package releases as v2.0.0 to match: Keystone, Atrium, Cortex, Impex, Keen, PennantPlus, Polycart, Roster and Showroom. Every `refactor-circus/*` constraint (require and require-dev) is `^2.0`, and every repo aliases `dev-main` to `2.0.x-dev`. Foundry and Guardian stay on Keystone `^0.1` until they migrate.
- Rationale: The user said "i'd like everything release as v2 to match". This supersedes any earlier choice to keep `^0.1` constraints or release Keystone as v0.2.0.
- Date: 2026-11-23
- Raised by: user

## D-016: Who owns Impex's Atrium front end?
- Choice: Impex does. The Vue module staged in Atrium (`ui/modules/impex`) moved into `atrium/` here (`@refactor-circus/impex-atrium`, the Atrium SDK a dev dependency pinned to a commit), typed by Impex's own `client/src/schema.d.ts` (the vendored copy is dropped). It builds into the committed `dist/atrium/`, declared in `composer.json` under `extra.atrium`, which Atrium discovers and injects into its Blade page. It is JavaScript only: still no Atrium PHP, no Composer require or suggest of Atrium, and nothing in `src/`, `config/`, `database/` or workbench PHP names Atrium. The "no Atrium" tests were narrowed to that, not deleted; `tests/Feature/AtriumModuleTest.php` checks the declared files and the build's source hash, and the CI `ui` job type-checks, tests, rebuilds and diffs it.
- Rationale: The user said "move the vue files for each module back into their respective modules. modules need to register their js. js should be injected into the blade file. modules are responsible for their own frontend and cjs clients from now on". It refines the spec's "API and MCP only, no UI" to "no PHP UI".
- Date: 2026-11-24
- Raised by: user
