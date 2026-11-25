Risk: high

## What changed
- Atrium removed: `src/Atrium/**`, Blade views, `lang/`, the `ui` and `atrium` config sections, `ImpexSupportFeature`, the `impex-views` and `impex-lang` publish tags, and all Atrium and PennantPlus tests, fixtures and the `without-atrium` CI lane. `ImpexServiceProvider` no longer wires any of it.
- New Run-domain API: `GET impex/stats`, `GET impex/search` and `GET impex/settings-summary`. Each has a controller, request, action, starting and finished events, and a resource. All three actions are in `MCP_EXCEPTIONS`.
- Committed `openapi.json`, exported by `dedoc/scramble` from the workbench. A workbench-only `ResourceResponses` extension writes the resource `schema()` as response schemas.
- Committed `client/` (`@refactor-circus/impex-client` 2.0.0, generated TypeScript), `composer client:generate`, and a CI `client` job.
- `composer.json` drops Atrium and PennantPlus requirements and adds `dedoc/scramble` as a dev dependency. README, CHANGELOG (v2.0.0 upgrade note), docs, AGENTS.md and the Boost skill are rewritten for API plus MCP.

## Why
Impex 2.0.0 ships only its JSON API and MCP server, and Atrium v2 hosts the UI. The three endpoints replace numbers the Blade UI computed in PHP, and the OpenAPI document plus client let Impex's Atrium module (prebuilt into `dist/atrium/`, loaded by Atrium v2's Vue app) call Impex with types. Decisions that shaped it:
- D-001: the API stays on by default.
- D-005: no MCP tools for the new endpoints.
- D-006: operator mode has no replacement in v2.0.0, so under `impex.authorization` stats and search cover the caller's own runs only.
- D-007: the Atrium VCS repository stays, because Cortex needs it.
- D-008: the client ships TypeScript source only.
- D-014: flows are checked against `ListFlowsTool::outputSchema()`, since there is no `FlowResource`.

## Look at
- `src/Domains/Run/Actions/ShowRunStatsAction.php:38`: owner scoping of status counts, failures and message counts, plus the `withMessages` null path.
- `src/Domains/Run/Actions/SearchRunsAction.php:1`: LIKE escaping of `%`, `_` and `\`, lowercase id prefix, ordering and limit.
- `src/Domains/Run/Actions/ShowSettingsSummaryAction.php:1`: the config whitelist, with a cast and default on each key.
- `src/ImpexServiceProvider.php:1`: removal of the Atrium, views, translations and Blade wiring.
- `composer.json:1`: dependency changes and the `client:generate` script.
- `workbench/app/OpenApi/ResourceResponses.php:1`: the route-to-resource map that drives `openapi.json`.

## Tested
- Criteria 1-3: `tests/Feature/Api/RunStatsApiTest.php`, `tests/Feature/Actions/ShowRunStatsActionTest.php`.
- Criteria 4-5: `tests/Feature/Api/RunSearchApiTest.php`, `tests/Feature/Actions/SearchRunsActionTest.php`.
- Criteria 6-7: `tests/Feature/Api/SettingsSummaryApiTest.php`, `tests/Feature/Actions/ShowSettingsSummaryActionTest.php`.
- Criteria 8-9: `tests/ArchTest.php`, `tests/Feature/EventsTest.php`, `tests/Feature/ApiTest.php`.
- Criteria 10-12 and 20: `tests/Feature/AtriumRemovedTest.php`, `tests/ArchTest.php`.
- Criterion 13: `tests/Feature/ComposerTest.php`.
- Criterion 14: the deletions in the diff and `tests/Feature/NoHistoryUiTest.php`.
- Criteria 15, 16 and 19: `tests/Feature/OpenApiTest.php`.
- Criteria 17-18: `tests/Feature/ClientTest.php`.
- Criteria 21-23: `tests/Feature/DocsTest.php`.
- Gate round 1 passed: `composer test` (phpstan clean, pint, pest 394/394), `lint:check` and `ringside check`. The CI `client` job and the npm typecheck are not run by `composer test`.

## Risks
- Breaking release: the removed config keys, `ImpexSupportFeature` and the publish tags break any host that used the Atrium UI. The CHANGELOG upgrade note covers this.
- Operators lose the all-runs view under `impex.authorization` (D-006).
- Export determinism: the staleness test depends on a byte-stable Scramble export.
- The 24-hour message counts filter on `occurred_at`. That column may need an index on large ledgers; this spec adds no migration.
- Cortex still pulls Atrium into `vendor/` (D-004, D-007).
- Nit (1): the new resources are in `Domains/Run/Http/Resources` while `RunResource` is in `Domains/Run/Resources`. The spec names the former.
