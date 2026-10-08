---
name: impex-development
description: >
  Build and operate workflows with the jayi/impex package in Laravel
  applications: writing flows and actions, making them safe to replay, keeping
  them inside serverless execution limits, and triggering them from the API,
  console, or scheduler.
license: MIT
metadata:
  author: Jay Fletcher
---

# Impex

Use this skill when a Laravel application runs multi-step work with `jayi/impex`
— writing a flow, adding an action, making long or large work survive a
serverless timeout, or tracking data crossing the application boundary.

## Primary Goal

- express the work as a deterministic flow whose every step is recorded, so a
  run survives worker restarts and at-least-once queue delivery without
  repeating side effects

## Workflow

### 1. Confirm the package is wired

- `jayi/impex` is in `composer.json`
- migrations are published: `php artisan vendor:publish --tag=impex-migrations`
- **`impex:tick` is on the schedule.** Without it, any run that sleeps or waits
  on a signal past the queue's delay ceiling never wakes. This is not optional.
  It also reclaims dead workers' step and batch item leases and retries
  subscription deliveries. Schedule `impex:prune` daily yourself.
- `impex.cache.store` points at a store that supports atomic locks and is shared
  across workers — Redis or DynamoDB on Vapor, never `array` or a per-instance
  store, because Lambda shares no memory between invocations

### 2. Write the flow

A flow is a class extending `JayI\Impex\Domains\Flow\Support\Flow` with a public `handle()`.
Register it in `config/impex.php` under `flows`, keyed by slug.

```php
final class ExtractProductsFlow extends Flow
{
    public function handle(string $query, int $limit = 50): array
    {
        $hits = $this->action(SearchProducts::class, $query, $limit)->run();

        [$pricing, $inventory] = $this->parallel()
            ->action(FetchPricing::class, $hits)
            ->action(FetchInventory::class, $hits)
            ->run();

        $this->action(WriteToPim::class, $hits, $pricing, $inventory)
            ->undoWith(RollbackPimWrite::class, $hits)
            ->run();

        return ['products' => count($hits)];
    }
}
```

`handle()` is **re-executed from the top on every resume**. Each DSL call is
keyed by its position in the replay, so the method must be deterministic: same
inputs, same sequence of calls, every time.

### 3. Write the actions

An action is any container-resolvable class with a public `execute()`. It has no
base class to extend and no interface to implement.

```php
final class FetchPricing
{
    public function __construct(private readonly PricingClient $client) {}

    /**
     * @param  array<int, array{sku: string}>  $hits
     * @return array<string, float>
     */
    public function execute(array $hits): array
    {
        return $this->client->quote(array_column($hits, 'sku'));
    }
}
```

Arguments and return values must be JSON-serializable — plain arrays and
scalars. Anything above `impex.artifacts.inline_threshold` is written to the
artifact disk automatically; you do not need to handle that.

### 4. Make it safe to replay

Anything that cannot be recomputed identically must be wrapped:

```php
$stamp = $this->sideEffect('started-at', fn () => now()->toIso8601String());
$batch = $this->sideEffect('batch-id', fn () => (string) Str::ulid());
```

Read `references/determinism.md` before writing a flow that branches on
anything other than a recorded step result.

### 5. Keep it inside the execution limits

Two different ceilings, two different tools:

- **A single action that may run long** — extend `ResumableAction`, check
  `shouldYield()` at a checkpoint, and `return $this->yieldTo($cursor)`. The
  engine stores the cursor, releases the lease, and re-dispatches the same step.
- **Work spread over many items** — `fanOut()` up to `impex.limits.fan_out_max`
  (default 100), `batch()` above it. Replay is O(history) per drive, so
  per-item steps do not scale.
- **A different workflow entirely** — `child()`. The child is a run in its own
  right with its own rollback, and the parent parks on it.
- **Work that may hang rather than fail** — a deadline. `expiresAt()` on a step,
  `expiresAt:` on a run, or defaults in `impex.deadlines`. Enforced by
  `impex:tick`, because a step inside an upstream call cannot check a clock.

Read `references/serverless.md` before writing anything whose size is not known
in advance.

### 6. Record what crosses the boundary

Inbound traffic becomes a channel in `impex.channels`; outbound goes through
an outbound channel with `Impex::send($channel, $message)` (or, for ad hoc
calls, `Impex::http($channel, $runId, $stepId)`) so every call lands in the
ledger with the run and step that made it. Name only the headers worth storing —
`store_headers` exists because webhook headers routinely carry credentials.

### 7. Trigger it

```php
Impex::run('extract-products', ['drill bits', 50], idempotencyKey: $requestId);
```

```
php artisan impex:run extract-products --argument="drill bits" --argument=50
```

Or put a cron expression in `impex.schedule` keyed by slug. A row in
`impex_flows` overrides that schedule and can disable the flow without a deploy.

Over HTTP, `POST impex/flows/{flow}/runs` answers `202` with the run. Over MCP,
`run-flow-tool` does the same — both call one Action. Add authentication middleware
to `impex.routes.middleware` and `impex.mcp.web.middleware` before exposing
either: they trigger and cancel workflows and read every recorded payload. With
`impex.authorization` on (the default), both act as the signed-in user: they
list only the runs that user owns, attach them as `owner` of runs they start,
and check every call against the model policies in `impex.policies` (a run's
owners may do anything; steps, owners, signals and messages follow the run).
Replace a policy by pointing its model at your own class there. Inbound channel
endpoints are signature-authenticated, never user-authorized.

### 8. Test it

The package ships assertions: `JayI\Impex\Testing\Flows`. `Flows::run()` drives
a run to completion in-process with no worker; `Flows::travelTo()` moves the
clock and runs the sweep, which is how you test a timeout, a sleep, or a
deadline. Write `Flows::redeliverSteps()` for any flow touching a
non-idempotent upstream — that is the test that at-least-once delivery does not
repeat a side effect.

### 9. Watch it

The dashboard renders through Atrium: define Atrium's `viewAtrium` gate and
Impex appears in its sidebar (set `impex.ui.enabled` to `false` to leave it
out). Atrium's gate decides who may load the dashboard, `impex.routes.middleware`
decides who may call the API. With `impex.authorization` on, the dashboard asks
the same policies as the API: a nav item, card or button shows only when its
action would be allowed (`@impexCan('cancel', $run)` in Blade, `ScreenAccess`
in PHP), and lists cover only the user's own runs. `impex.atrium.show_all`
(`true`, or a Gate ability name) makes users dashboard operators, who see every
run (unowned ones included) and may use every Impex control — on the Atrium
screens only, never the API or MCP. With `jayi/pennantplus`,
`Feature::for(null)->deactivate(ImpexSupportFeature::class)` hides Impex in
Atrium (`impex.atrium.features`). Statuses are Atrium status dots coloured by
`JayI\Impex\Atrium\Badges` (`info` only for pending/waiting, through the
`impex::ui.partials.status-dot` partial); actions are icon buttons. Impex ships
no stylesheet and no components: views use `x-atrium::*` components and Atrium's
safelisted utilities only, never `<style>` or `style=` (a test runs
`AtriumStyles::missingClasses()` / `inlineStyles()`). The run and message pages
show `<x-atrium::audit-trail source="impex" :subject="..." />`, the runs page the
package-wide trail; they render nothing until jayi/keen is installed. Over MCP the tools sit behind `search_tools` and
`execute_tools`; use `list-runs-tool` and `show-run-tool`. A run at `waiting` is
blocked on a signal or a timer, not stuck. A failed run may have rolled back —
check the steps with phase `rollback` to see what was rolled back. With an audit
log (jayi/keen) installed, `GET impex/history` and `list-impex-history-tool`
show who changed what; without one they answer "not installed".

## Rules, References, and Templates

Read before executing:

- `references/determinism.md` — the replay contract and what breaks it
- `references/serverless.md` — timeouts, payload limits, long waits, resume

The package's own `docs/` directory carries the full reference: the DSL
(`02-flows.md`), rollback (`04-rollback.md`), scale (`06-scale.md`),
the ledger (`07-ledger.md`), the API (`09-api.md`), MCP (`10-mcp.md`),
extending (`12-extending.md`), every config key (`13-configuration.md`),
every table (`14-schema.md`), channels and transports (`18-channels.md`), and
subscriptions (`19-subscriptions.md`).

## Queue middleware and batch items

Give Impex's jobs (`DriveRun`, `ExecuteStep`, `SeedBatch`, `ProcessBatchItem`,
`DetectStream`, `DeliverSubscription`, `ExportSubscription`, in
`JayI\Impex\Jobs`) queue middleware through config, never by subclassing them:

```php
'jobs' => [
    'middleware' => [
        '*' => [\App\Queue\TagWithTenant::class],                      // every job
        \JayI\Impex\Jobs\DeliverSubscription::class => [
            [\Illuminate\Queue\Middleware\RateLimited::class, 'impex-deliveries'], // [class, ...args]
            \App\Queue\SubscriptionLocks::class,                       // a JobMiddlewareFactory
        ],
    ],
],
```

A `JayI\Impex\Contracts\JobMiddlewareFactory` implements
`middleware(object $job): array` to build middleware from the job in hand.
Entries are class names or arrays only, so the config caches; they are read
when the job runs.

Batch items are leased while `ProcessBatchItem` runs. `impex:tick` reclaims an
item whose worker died once its lease lapses (`BatchRunner::reclaimLeases()`),
counting the attempt, so a poison item fails (`BatchItemAbandonedException`)
rather than blocking its batch. `ProcessBatchItem` implements Laravel's
`Interruptible` (Laravel `^13.34`, `pcntl`): on `SIGALRM` — the worker timing
it out — it abandons the item at once for an immediate retry; on `SIGTERM` it
lets the item finish. Size `tries()` on a batch knowing that a killed attempt
counts.

## Channels, transports and subscriptions

Impex is the application's one way in and out. Send everything outbound
through a channel so it is recorded:

```php
Impex::send('carrier-api', ['zip' => '90210']);                 // http, JSON
Impex::send('ops-mail', OutboundMessage::mail(new LowStock($sku))); // mail
Impex::send('partner-sftp', new OutboundMessage(stream: $handle));  // file
```

Channels live in `impex.channels` or `impex_channels` (runtime, via the
`/impex/channels` API or MCP). Outbound HTTP through a channel with a secret is
signed (Standard Webhooks). Wrap the default mailer in the `impex` mail
transport to record every mail.

Impex has no dependency on any domain package; any app can use all of it. To
push an app's own data to subscribers, write a stream (extend `AbstractStream`;
`StreamKind::Append` for events such as orders), register it and report change.
A subscriber following several streams holds one subscription per stream:

```php
Impex::streams()->register(ProductStream::class);       // extends AbstractStream
Impex::streams()->touch('keystone.products', $identifiers);   // snapshot stream
Impex::streams()->publish('orders', $number, 'order.shipped', $payload); // append stream
```

A stream's `snapshots()` must load a whole chunk in a constant number of
queries; its `matcher()` must index subscription filters, never expand them.
Subscribers manage subscriptions on `/impex/subscriber/...` behind the app's
OAuth middleware (`impex.routes.subscriber_middleware`).

Pausing a flow (its `impex_flows` row, `enabled = false`) stops every
trigger: `Impex::run()` throws `DisabledFlowException`, and an inbound channel
bound to it answers `503` with `Retry-After`. The row's `queue` and
`queue_connection` route new runs, and its `defaults` fill `handle()` parameters
a caller left out, by name. Stored channels can be managed on the
Atrium Channels screens. Measure the subscription pipeline with
`vendor/bin/testbench impex:bench` in the package workbench.

## Examples

- a supplier webhook lands on an inbound channel, which records the payload as a
  message and starts a flow that enriches ~40 SKUs with `fanOut()` and writes
  them to the PIM with a registered rollback
- a nightly catalogue sweep uses `batch()` over a million-row cursor whose seeder
  extends `ResumableAction` and checkpoints every few thousand rows, finishing
  across dozens of invocations without any one exceeding the platform ceiling
- a purchase-order flow performs two steps, then `awaitSignal('approval')` with a
  three-day timeout; the run sits at `waiting` costing nothing until a human
  approves it or `impex:tick` fires the timeout

## Anti-patterns

- **do not** call `Http::` or `Mail::` directly for traffic leaving the
  application — send through `Impex::send()` (or `Impex::http()` for ad hoc
  calls) so it lands in the ledger
- **do not** reorder or remove a stream's topics: their order is stored as bits
- **do not** key a stream by anything that can change, such as a SKU: a renamed
  key reads as one subject removed and another added, and breaks subscribers'
  lists. Key by a permanent id and carry the SKU as data
- **do not** offer a per-account stream (each customer's own orders) without
  guarding it: Impex applies a stream's matcher only to filtered subscriptions,
  so an unfiltered or subject-listed one bypasses it. Listen to
  `SubscriptionCreatingActionEvent`, `SubscriptionUpdatingActionEvent` and
  `SubscriptionSubjectsUpdatingActionEvent` and throw a `ValidationException`
  for an unfiltered or listed subscription to it. Bind the account through the
  subscriber's owner, never through the filter
- **do not** load snapshots one subject at a time, or expand a subscription's
  filter into the subjects it covers; both break at catalogue scale

- **do not** call `now()`, `rand()`, `Str::ulid()`, or an unrecorded query
  directly in `handle()` — wrap them in `sideEffect()` or the run will diverge
  on resume
- **do not** subclass an Impex job to add queue middleware — use
  `impex.jobs.middleware`
- **do not** catch `JayI\Impex\Domains\Run\Support\Suspended` in flow code; it is the
  engine's control flow, and catching it corrupts the run
- **do not** put business logic in `handle()` — it belongs in an action, because
  `handle()` re-runs on every drive while an action runs once
- **do not** pass Eloquent models or closures as action arguments; pass
  identifiers and re-resolve inside the action
- **do not** use a closure for `undoWith()` — the rollback is captured
  when the forward step is recorded, so it must be a class name
- **do not** assume an action runs exactly once because the step is leased: a
  lapsed lease is reclaimable by design, so any action touching a
  non-idempotent upstream still needs its own idempotency key
- **do not** `fanOut()` over an unbounded collection — use `batch()`
- **do not** deploy a changed `handle()` while runs of that flow are live
  without versioning it: declare `public const VERSION` and branch on
  `$this->version()`, or drain the active runs first. Otherwise the replay
  diverges and the run fails with `HistoryMismatchException`
- **do not** reach for `runSync()` or `wait: true` for anything but short flows
  and tests — the gateway times out long before a real flow finishes
- **do not** use `rollbackTogether()` unless the group's steps are genuinely
  independent; reverse order exists because rollbacks usually depend on it
