# Impex

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `jayi/impex`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Layout

The package follows the mono domain-module layout (see `/Users/jay/Herd/mono/agent-os/standards/architecture/domain-modules.md`). Code lives in `src/Domains/{Run,Flow,Signal,Batch,Channel,Message,Subscription,Artifact}` (namespace `JayI\Impex\Domains\{Domain}`), each with its own `{Domain}ServiceProvider` registered by `Domains\DomainServiceProvider`; the domain providers extend `JayI\Foundation\Support\ServiceProvider`, whose `loadApiRoutesFrom()` loads their routes into the shared `impex.` API group (the inbound channel routes keep their own middleware group in `MessageServiceProvider`). Models are named `{Entity}Model` and keep their old class names as morph aliases. The engine and its collaborators live in `Domains\Run\Services`; the flow base classes and DSL builders in `Domains\Flow\Support`. The Atrium screens span every domain and live in `src/Atrium`; package-wide pieces (`Impex`, the facade, `ImpexException`, `Mcp\ImpexServer` and `Mcp\Tools\ListImpexHistoryTool`, `Support\`, `Testing\Flows`, `impex:prune`) stay at the top level. The queued jobs stay in `src/Jobs` because their class names are inside queued payloads. `config/impex.php` stays one file.

## Foundation

Impex stands on `jayi/foundation`, the suite's shared runtime. Use its classes rather than adding local copies:

- `ImpexServiceProvider` extends `JayI\Foundation\Support\PackageServiceProvider`: `definition()` describes the package (`impex`, `JayI\Impex`, `ImpexServer`, Gate authorization by default), `registerPackage()` runs right after `mergeConfigFrom()` and before the domain providers, and `boot()` uses `registerCortex()`, `registerPolicies()`, `registerAtriumPlugin()`, `registerMcpServer()` and `loadHistoryRoutes()` (`GET impex/history`, `impex.history.index`).

## Atrium screens

- Atrium owns every component and style. Impex ships no `resources/css`, no `Atrium::css()` call and no Blade components: views use `x-atrium::*` components and Atrium's safelisted utilities only, never `<style>` or `style=`. `tests/Feature/Ui/StylesTest.php` asserts `AtriumStyles::missingClasses()` and `inlineStyles()` are empty; when a class is missing, use the closest safelisted one and ask for it in Atrium.
- Details are `x-atrium::description-list`, the post-action status and error `x-atrium::flash`, states the `impex::ui.partials.status-dot` partial (colours from `Atrium\Badges`).
- Show screens end with `<x-atrium::audit-trail source="impex" :subject="$model" />`, the runs index with the package-wide trail. Run steps are execution, not changes, and stay out of it.
- `ImpexPlugin::features()` is `featuresFromConfig('impex.atrium.features')`; `key()`/`label()` come from the base. `Atrium\ScreenAccess::allows()` lets operators (`impex.atrium.show_all`) through and otherwise delegates to `JayI\Atrium\Support\ScreenAccess::allows('impex', ...)`; calls with policy arguments go to the authorizer directly. Impex keeps its own `AuthorizesScreens` because it passes those arguments and honours operators.
- Events implement `JayI\Foundation\Contracts\{ActionStartingEvent,ActionFinishedEvent,ModelLifecycleEvent}`; models use `JayI\Foundation\Models\Concerns\DispatchesModelEvents`.
- HTTP requests extend `JayI\Foundation\Http\Requests\Request`, MCP requests `JayI\Foundation\Mcp\Requests\Request`, tools `JayI\Foundation\Mcp\Tool`; `ImpexServer` extends `JayI\Foundation\Mcp\Server` and lists `ListImpexHistoryTool`.
- Outside a request, get the authorizer with `Authorizer::for(app(PackageRegistry::class)->get('impex'))`, and the Cortex bridge with `CortexIntegration::for(...)`.
- `ImpexException` extends `JayI\Foundation\Exceptions\PackageException`, so every Impex exception answers `409` with its message over HTTP and returns its message over MCP.
- `Support\Policies\Policy` extends `JayI\Foundation\Policies\Policy`; use `allowsOn()` for a child model and `allowsOnRun()` for a model that may have no run.
- `Domains\Flow\Support\ResumableAction` is Impex's own flow primitive, not a Foundation action.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.

## Engine Invariants

These are load-bearing. Changing any of them changes correctness, not style.

- `handle()` is replayed from the top on every drive. Steps are keyed by their
  position in the replay, so the DSL call order must be deterministic. Anything
  unrecomputable goes through `sideEffect()`.
- A step is **claimed before it executes**. A unique `(run_id, phase, sequence)`
  alone does not make at-least-once delivery safe, because a record-after-execute
  step calls the upstream before it can lose an insert race.
- A lapsed lease is reclaimable **by design** — that is what stops a killed
  invocation wedging a run. The guarantee is at-most-once per lease, not
  exactly-once. Say so in docs rather than implying otherwise.
- `lease_seconds` must stay above `max_step_seconds`, or a legitimately slow
  step is reclaimed while still working and runs twice.
- Jobs carry identifiers only, never payloads. There is an arch test for this.
  Every job uses `Support\Concerns\UsesConfiguredMiddleware`, so applications
  add queue middleware per job class through `impex.jobs.middleware`; a new job
  uses it too.
- Anything leased — steps, batch items, touched subjects — is reclaimed by
  `impex:tick` once its lease lapses, and counts the dead attempt. A job may
  release early on a timeout (`Interruptible`, `SIGALRM`), never on `SIGTERM`,
  which lets the work finish.
  Anything above the inline threshold goes to the artifact disk.
- Waits longer than the queue's delay ceiling are timer rows, never delayed
  jobs. `impex:tick` is required, not optional.
- Timer and lease claims use a lease predicate, never a bare `claimed_at IS NULL`
  — that strands the row forever if the claimer dies mid-dispatch.
- Artifacts use `Prunable`, not `MassPrunable`: mass pruning never fires
  `pruning()` and would orphan every stored object.
- Artifact retention must never be shorter than the retention of the rows
  pointing at artifacts.
- A rollback is captured when the forward step is recorded, so unwinding never
  replays the flow. That is why `undoWith()` takes a class, not a closure.
- The vocabulary is deliberately plain: unit, rollback, undo. Do not reintroduce
  saga jargon.
- Replay is O(history) per drive. Per-item fan-out is capped; large collections
  use `batch()`, whose per-item state lives outside the replay log.
- Engine collaborators are resolved from the container, not newed up. Adding
  behaviour means a new collaborator or a decorated contract, not another method
  on Engine.
- Impex is the application's one way in and out. Anything a package sends —
  webhooks, API calls, feeds, mail, subscriber pushes — goes through a channel
  and its transport so it lands in the ledger. Domain packages add a `Stream`;
  the generic machinery (channels, transports, subscriptions, delivery) lives
  here.
- The ledger write is the hot path: one query-builder insert, no per-row model
  events. Keep it that way.
- Subscriptions scale with changes, never with catalogue size times
  subscribers: nothing per subscriber on the write path, filters matched as
  rules (a stream's `SubscriptionMatcher`) and never expanded into rows,
  fan-out one narrow `(subscription_id, event_id)` row per match, payloads
  built at send time. Every stage works in chunks with a constant number of
  queries; there is a query-count test for detection.
- A stream's `topics()` order is stored as bits. Append topics; never reorder
  or remove one.
- A subscription's cursor moves only on a 2xx, and one delivery runs per
  subscription at a time, so a subscriber sees batches in order.
- Table names are hardcoded, matching cortex. A configurable prefix would break
  the literal table names in the static `rules()` convention.
