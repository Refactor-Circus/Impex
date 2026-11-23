# HTTP API

```php
// config/impex.php
'routes' => [
    'enabled' => true,
    'prefix' => 'impex',
    'middleware' => ['api'],
    'channel_middleware' => ['api'],
    'subscriber_middleware' => ['api'],
],
```

Three surfaces, three middleware stacks:

| Surface | Prefix | Middleware | Who calls it |
|---|---|---|---|
| Operator API | `impex/` | `middleware` | Your users and systems |
| Inbound channels | `impex/channels/{channel}` and custom paths | `channel_middleware` | Upstreams, authenticated by signature |
| Subscriber API | `impex/subscriber/` | `subscriber_middleware`, then `ResolveSubscriber` | Subscribers, authenticated as OAuth clients |

**Add authentication middleware before exposing these.** The operator API
triggers and cancels workflows and reads every payload that has crossed the
boundary. Put your OAuth middleware on `subscriber_middleware`; see
[Subscriptions](19-subscriptions.md#the-subscriber-api).

With `impex.authorization` on (the default), every operator call is also
checked against the model policies in `impex.policies`: each user sees only the
runs they own, owns the runs they start, and is refused anything their policy
denies. See [Ownership](08-ownership.md#authorizing-the-api) for the ability
each endpoint checks. The inbound channel endpoints are never user-authorized:
they authenticate with the channel's signing secret. The subscriber API is
scoped to the calling subscriber instead.

Every endpoint is one line of controller: validation rules come from an Action's
static `rules()`, and the request's `persist()` calls that same Action. The MCP
surface calls the same Actions, so the two cannot drift.

The operator API is described by `openapi.json` and wrapped by the
`@refactor-circus/impex-client` TypeScript package; see
[OpenAPI and the TypeScript client](#openapi-and-the-typescript-client).

## Flows

### `GET impex/flows`

```json
{
  "data": [
    {
      "slug": "extract-products",
      "class": "App\\Flows\\ExtractProductsFlow",
      "enabled": true,
      "schedule": "0 * * * *"
    }
  ]
}
```

### `POST impex/flows/{flow}/runs`

```json
{
  "arguments": ["drill bits", 50],
  "idempotency_key": "req_9f2c",
  "tags": { "tenant": "acme" },
  "version": "v2",
  "expires_in": 3600,
  "wait": false
}
```

`version` pins the run so later code changes can branch on it. `expires_in` is a
deadline in seconds. `wait` drives the run inline and returns its terminal
state — only for short flows, and bounded by `impex.limits.sync_seconds`, because
the gateway will time out long before a flow of any size finishes.

`202 Accepted` — the run is queued, never executed inline, so the caller is not
held past the gateway's timeout.

```json
{
  "data": {
    "id": "01JQ7X…",
    "flow": "extract-products",
    "status": "pending",
    "trigger": "api",
    "idempotency_key": "req_9f2c",
    "tags": { "tenant": "acme" },
    "error": null,
    "parent_run_id": null,
    "started_at": null,
    "finished_at": null,
    "created_at": "2026-08-17T09:14:02+00:00"
  }
}
```

Reusing an idempotency key returns the original run rather than starting a
second one. A flow disabled by an `impex_flows` override is refused — from
every trigger, not only this endpoint: `Impex::run()` refuses it too, and an
inbound channel bound to it answers `503` with `Retry-After`, so the sender
retries once the flow is back on.

## Runs

### `GET impex/runs`

| Filter | Notes |
|---|---|
| `status` | `pending` `running` `waiting` `rolling_back` `completed` `failed` `cancelled` |
| `flow` | slug |
| `trigger` | `api` `mcp` `command` `schedule` `channel` `code` `child` |
| `owner_type` + `owner_id` | both required together |
| `tag[key]=value` | repeatable |
| `since`, `until` | dates, against `created_at` |
| `parent` | a run id; returns its children |
| `cursor`, `per_page` | cursor pagination, 1–200 |

```
GET impex/runs?status=failed&flow=extract-products&tag[tenant]=acme&per_page=50
```

Cursor pagination, not offset: a ledger only grows, and deep offsets degrade.

### `GET impex/runs/{run}`

Includes `owners` and the forward `steps`.

```json
{
  "data": {
    "id": "01JQ7X…",
    "flow": "extract-products",
    "status": "completed",
    "owners": [
      { "id": "01JQ…", "owner_type": "App\\Models\\Customer", "owner_id": "42", "role": "customer" }
    ],
    "steps": [
      {
        "id": "01JQ…",
        "phase": "forward",
        "sequence": 0,
        "type": "action",
        "name": "App\\Flows\\Actions\\SearchProducts",
        "status": "completed",
        "attempts": 1,
        "max_attempts": 1,
        "resumptions": 0,
        "rolled back": false,
        "undoes_sequence": null,
        "error": null,
        "has_result": true,
        "result_artifact_id": null,
        "started_at": "2026-08-17T09:14:03+00:00",
        "completed_at": "2026-08-17T09:14:05+00:00"
      }
    ]
  }
}
```

**Payloads are never inlined.** `has_result` tells you a result exists;
`result_artifact_id` points at it when it was too large for a column. A step
result can be hundreds of megabytes.

### `POST impex/runs/{run}/cancel`

```json
{ "reason": "Superseded by run 01JQ8…" }
```

A run that has already finished is returned unchanged.

### `POST impex/runs/{run}/retry`

`202`. Re-queues a drive. Completed steps are not re-executed.

## Steps

### `GET impex/runs/{run}/steps`

`?phase=forward` or `?phase=rollback`. Omit for both, ordered by phase then
sequence.

## Signals

### `POST impex/runs/{run}/signals`

```json
{
  "name": "approval",
  "payload": { "approved": true, "by": 42 },
  "idempotency_key": "evt_9f2",
  "if_running": false
}
```

`202` on delivery. A signal delivered before the run reaches its wait is held,
not lost, and is accepted by any unfinished run — `pending`, `running`, or
`waiting`.

`409` if the run has already finished: a conflict rather than a validation
error, because the request was well-formed and the run's state made it
impossible. Set `if_running: true` to get `200` with `data: null` instead.

## Owners

```
GET    impex/runs/{run}/owners
POST   impex/runs/{run}/owners
DELETE impex/runs/{run}/owners/{owner}
```

```json
{ "owner_type": "App\\Models\\Customer", "owner_id": "42", "role": "customer" }
```

`201` on create, `204` on delete. `{owner}` is the owner **record** id from the
index, not the model's key.

## Messages

### `GET impex/messages`

Filters: `direction`, `channel`, `run`, `since`, `until`, `cursor`, `per_page`.

```json
{
  "data": [
    {
      "id": "01JQ…",
      "run_id": "01JQ7X…",
      "step_id": null,
      "direction": "inbound",
      "channel": "supplier-feed",
      "transport": "http",
      "endpoint": "https://app.test/impex/channels/supplier-feed",
      "method": "POST",
      "status_code": null,
      "headers": { "content-type": ["application/json"] },
      "bytes": 2048,
      "body_preview": "{\"sku\":\"ABC-1\"…",
      "body_artifact_id": null,
      "body_encoding": "utf8",
      "body_sha256": "9f86d08188…",
      "delivery_id": null,
      "signature_valid": true,
      "duration_ms": null,
      "error": null,
      "occurred_at": "2026-08-17T09:14:02+00:00"
    }
  ]
}
```

`delivery_id` links an outbound row to the subscription delivery that sent it.
The full body is never in a listing; read it with `Impex::body()`.

### `GET impex/messages/{message}`

## Channels

Configured channels are read-only here; stored ones can be created, changed and
deleted. See [Channels and transports](18-channels.md).

### `GET impex/channels`

Every channel, inbound and outbound, configured and stored. Filter with
`?direction=inbound` or `outbound`. With authorization on, a user sees the
application's channels and their own, never another owner's.

```json
{
  "data": [
    {
      "id": null,
      "name": "supplier-feed",
      "direction": "inbound",
      "transport": "http",
      "status": "active",
      "body_policy": "all",
      "verifies_signatures": true,
      "flow": "extract-products",
      "path": null,
      "options": [],
      "stored": false,
      "owner_type": null,
      "owner_id": null
    }
  ]
}
```

Secrets are never returned: `credentials` is not part of a channel's shape.

### `GET impex/channels/{name}`

One channel, configured or stored, in the same shape.

### `POST impex/channels`

```json
{
  "name": "warehouse-api",
  "direction": "outbound",
  "transport": "http",
  "status": "active",
  "body_policy": "failures",
  "options": { "url": "https://wms.example.com/events", "timeout": 5 },
  "credentials": { "signing_secret": "whsec_…" }
}
```

`201` with the channel. `name` is lowercase letters, digits, `.`, `_` and `-`,
and unique. A name already in `impex.channels`, or a transport that is not
registered, answers `409`. With authorization on the channel belongs to the
caller, so its URL is held to the [endpoint guard](18-channels.md#the-endpoint-guard)
— an unsafe one answers `422`.

### `PATCH impex/channels/{channel}`

`transport`, `status`, `body_policy`, `options`, `credentials`. The name and
direction are fixed. Stored channels only: a configured name answers `404`.

### `DELETE impex/channels/{channel}`

`204`. The channel's traffic stays in the ledger.

### `POST impex/channels/{channel}/rotate-secret`

A new signing secret, keeping the previous one valid until the next rotation.
The response is the only one that carries a secret:

```json
{ "data": { "name": "warehouse-api", "…": "…" }, "secret": "whsec_…" }
```

### `POST impex/channels/{channel}` — receiving

The inbound endpoint, on `channel_middleware`. A configured channel with a
`path` is also served there. See [The ledger](07-ledger.md#inbound-channels).

## Streams

### `GET impex/streams`

What subscribers can follow.

```json
{
  "data": [
    {
      "key": "catalogue.products",
      "kind": "snapshot",
      "topics": ["content", "pricing", "assets"],
      "formats": ["thin", "slice", "full"],
      "filters": ["categories"]
    }
  ]
}
```

## Subscribers

```
GET    impex/subscribers                 ?status=&cursor=&per_page=
POST   impex/subscribers
GET    impex/subscribers/{subscriber}
PATCH  impex/subscribers/{subscriber}
DELETE impex/subscribers/{subscriber}
```

```json
{ "name": "Acme Tools", "client_id": "9c1e…", "status": "active", "metadata": { "account": "ACME-01" } }
```

`201` on create, `204` on delete. `client_id` is the OAuth client the
subscriber authenticates as on the subscriber API, and is unique. `show`
includes `subscriptions_count`. Deleting a subscriber deletes its
subscriptions and their endpoint channels.

```json
{
  "data": {
    "id": "01JQ2A…",
    "name": "Acme Tools",
    "client_id": "9c1e…",
    "status": "active",
    "owner_type": "App\\Models\\User",
    "owner_id": "7",
    "metadata": { "account": "ACME-01" },
    "subscriptions_count": 2,
    "created_at": "2026-10-07T09:14:02+00:00"
  }
}
```

## Subscriptions

The operator creates a subscription for a subscriber:

```
POST   impex/subscribers/{subscriber}/subscriptions
```

and manages it on these routes, which the [subscriber API](#subscriber-api)
serves too under its own prefix:

| Method | Path | Body or query | Answers |
|---|---|---|---|
| `GET` | `subscriptions` | `stream`, `status`, `subscriber`, `cursor`, `per_page` | Cursor paginated |
| `GET` | `subscriptions/{subscription}` | | |
| `PATCH` | `subscriptions/{subscription}` | `topics`, `format`, `filter`, `options`, `status` (`active`/`paused`), `replay_from`, `endpoint.url`, `endpoint.to` | |
| `DELETE` | `subscriptions/{subscription}` | | `204` |
| `POST` | `subscriptions/{subscription}/ping` | | `{data: {successful, status_code, duration_ms, error, message_id}}` |
| `POST` | `subscriptions/{subscription}/export` | | `202` with the subscription |
| `POST` | `subscriptions/{subscription}/rotate-secret` | | The subscription, with `secret` |
| `POST` | `subscriptions/{subscription}/subjects` | `add`, `remove` (up to 10,000 each) | `{data: {added, removed}}` |
| `GET` | `subscriptions/{subscription}/events` | `after`, `limit` | `{data, cursor, has_more}` |
| `GET` | `subscriptions/{subscription}/deliveries` | `status`, `cursor`, `per_page` | Cursor paginated, newest first |

Creating takes `stream`, `topics`, `format`, `filter`, `subjects`, `options`
and `endpoint` (`transport`, `url`, `to`), and answers `201` with the signing
secret beside the subscription — the only time it is returned. The request and
response shapes, the feed and the delivery envelope are in
[Subscriptions](19-subscriptions.md).

A subscription:

```json
{
  "data": {
    "id": "01JR8K…",
    "subscriber_id": "01JQ2A…",
    "stream": "catalogue.products",
    "topics": ["pricing", "assets"],
    "selection": "filter",
    "filter": { "categories": ["power-tools"] },
    "format": "slice",
    "options": null,
    "status": "active",
    "endpoint": { "channel": "subscription-01JR8K…", "transport": "http", "url": "https://vendor.example.com/hooks" },
    "cursor": "1187",
    "failures": 0,
    "pending": false,
    "paused_until": null,
    "disabled_at": null,
    "last_delivered_at": "2026-10-07T09:20:41+00:00",
    "last_error": null,
    "last_export_path": null,
    "last_export_at": null,
    "created_at": "2026-10-07T09:14:02+00:00"
  }
}
```

`endpoint` is `null` for a feed-only subscription. A delivery:

```json
{
  "id": "01JR8M…",
  "subscription_id": "01JR8K…",
  "first_event_id": "1041",
  "last_event_id": "1187",
  "events": 100,
  "status": "succeeded",
  "message_id": "01JR8M…",
  "status_code": 200,
  "duration_ms": 84,
  "error": null,
  "attempted_at": "2026-10-07T09:20:41+00:00"
}
```

`message_id` is the ledger row of the request that delivery sent.

## Subscriber API

Under `impex/subscriber`, on `subscriber_middleware`. The calling subscriber is
resolved from its OAuth client; a request that resolves to no active
subscriber answers `403`. A subscriber may touch only its own subscriptions.

```
GET    impex/subscriber/streams
POST   impex/subscriber/subscriptions
GET    impex/subscriber/subscriptions
GET    impex/subscriber/subscriptions/{subscription}
PATCH  impex/subscriber/subscriptions/{subscription}
DELETE impex/subscriber/subscriptions/{subscription}
POST   impex/subscriber/subscriptions/{subscription}/ping
POST   impex/subscriber/subscriptions/{subscription}/export
POST   impex/subscriber/subscriptions/{subscription}/rotate-secret
POST   impex/subscriber/subscriptions/{subscription}/subjects
GET    impex/subscriber/subscriptions/{subscription}/events
GET    impex/subscriber/subscriptions/{subscription}/deliveries
```

Same bodies and responses as the operator routes above. See
[Subscriptions](19-subscriptions.md#the-subscriber-api).

## Dashboard endpoints

Three read endpoints give a UI such as [Atrium v2](11-dashboard.md) the
figures it shows, so it never computes them itself. Each authorizes `viewAny`
on `RunModel`: with `impex.authorization` on, a guest gets `401` and a user
the policy refuses `403`, and every count, failure and search result covers
only the runs the caller owns (and those runs' messages). With authorization
off nothing is scoped. They only read, and have no MCP tools: agents use
`list-runs-tool` and its filters.

### `GET impex/stats`

`impex.stats.show`. Runs per status, the active run count, the latest
failures, and the message volume over the last day:

```json
{
  "data": {
    "runs": {
      "by_status": {
        "pending": 2, "running": 1, "waiting": 4, "rolling_back": 0,
        "completed": 318, "failed": 7, "cancelled": 3
      },
      "active": 7,
      "recent_failures": [
        { "id": "01JQ9B…", "flow": "extract-products", "status": "failed", "…": "…" }
      ]
    },
    "messages": { "window_hours": 24, "inbound": 1204, "outbound": 388 }
  }
}
```

- `by_status` has a key for every run status, zero when no run has it.
- `active` counts the runs that may still be driven or signalled
  (`RunModel::query()->active()`).
- `recent_failures` holds up to five failed runs, newest `finished_at` first,
  each shaped like a run in `GET impex/runs`.
- `messages` counts ledger rows in each direction whose `occurred_at` is
  within the last 24 hours. It is `null` when the caller may not `viewAny`
  `MessageModel`.

### `GET impex/search`

`impex.search.index`. Runs whose `flow` contains `q`, or whose `id` starts with
it (compared in lowercase), newest `created_at` first, then by id.

| Parameter | Rules |
|---|---|
| `q` | Required. Trimmed, then 1–255 characters. `%`, `_` and `\` match literally. |
| `limit` | Optional integer, 1–25, default 5. |

```
GET impex/search?q=extract&limit=10
```

```json
{ "data": [ { "id": "01JQ7X…", "flow": "extract-products", "status": "completed", "…": "…" } ] }
```

A plain collection of runs, in the `GET impex/runs` shape, with no pagination.
A missing or blank `q`, or a `limit` out of range or not an integer, answers
`422`.

### `GET impex/settings-summary`

`impex.settings-summary.show`. The effective engine settings, read from config
with a cast and a default for each key:

```json
{
  "data": {
    "retention": {
      "completed_runs_days": 90, "failed_runs_days": 365, "messages_days": 90,
      "artifacts_days": 365, "events_days": 30, "deliveries_days": 30
    },
    "limits": {
      "max_step_seconds": 840, "resume_margin_seconds": 30, "lease_seconds": 900,
      "lock_seconds": 120, "max_resumptions": 10000, "fan_out_max": 100,
      "sync_seconds": 15
    },
    "artifacts": { "disk": null, "path": "impex", "inline_threshold": 65536, "memo_bytes": 16777216 },
    "preview_bytes": 2048
  }
}
```

Only these keys are sent. A key your application adds under `retention`,
`limits` or `artifacts` is never exposed. `artifacts.disk` is `null` when the
application's default disk is used.

## OpenAPI and the TypeScript client

`openapi.json`, at the package root, is an OpenAPI 3.1 document of every
`impex.*` route served under `impex.routes.prefix`. Each operation's
`operationId` is its route name (`impex.runs.index`, `impex.stats.show`, ...),
and the success responses of `impex.runs.index`, `impex.runs.show`,
`impex.messages.index`, `impex.flows.index`, `impex.channels.index`,
`impex.subscriptions.index`, `impex.stats.show`, `impex.search.index` and
`impex.settings-summary.show` carry their resources' schemas. It is exported by
[Scramble](https://scramble.dedoc.co) from the package's Testbench workbench.
Scramble is a development dependency; nothing in `src/` or `config/` uses it.

`client/` is `@refactor-circus/impex-client`, a typed client generated from
that document by `openapi-typescript`:

```ts
import { createImpexClient } from '@refactor-circus/impex-client';

const impex = createImpexClient({
  baseUrl: 'https://app.test/impex',   // the host's URL and impex.routes.prefix
  headers: { Authorization: `Bearer ${token}` },
});

const { data: stats } = await impex.GET('/stats');
const { data: hits } = await impex.GET('/search', { params: { query: { q: 'extract' } } });
```

`createImpexClient(options)` takes `baseUrl` plus any of `openapi-fetch`'s
`ClientOptions` and returns its `Client<paths>`; the `paths`, `components` and
`operations` types are exported too. The package ships TypeScript source with
no build step, and is not on an npm registry. Pin it as a git dependency on the
Impex tag your host runs; npm cannot install a subfolder of a repository, so
use pnpm (`github:refactor-circus/impex#v2.0.0&path:/client`) or Bun. See
`client/README.md`.

Both files are generated. After changing a route, request, Data or resource,
regenerate them in the package and commit the result:

```bash
composer client:generate
```

It runs `scramble:export` through `vendor/bin/testbench` into `openapi.json`,
`npm ci` and `npm run generate` in `client/` (which writes
`client/src/schema.d.ts`), and writes `client/openapi.sha256`. A Pest test
fails when a fresh export differs from `openapi.json` or the checksum does not
match it; the CI `client` job regenerates everything, fails on any diff and
type-checks the client.

## History

### `GET impex/history`

Impex's audit entries, newest first: who did what, through which surface, and
which fields changed. Filter with `subject_type` and `subject_id` for one
record, or `action`; cursor paginated with `cursor` and `per_page`. The route
comes from refactor-circus/keystone and answers only while Impex audits: an
audit log ([refactor-circus/keen](https://github.com/Refactor-Circus/Keen))
installed and `impex.audit.enabled` on. Otherwise it answers `404`:

```json
{ "message": "No audit log is installed. Install refactor-circus/keen to record history." }
```

## Errors

A broken Impex rule (an `ImpexException`, such as a disabled flow or a signal to
a finished run) answers `409` with its message:

```json
{ "message": "The flow [extract-products] is disabled. Re-enable it by removing or updating its row in impex_flows." }
```

A record that does not exist answers `404` with `{ "message": "Not found." }`.
A guest answers `401` while `impex.authorization` is on; a signed-in user the
policy refuses, `403`.

Validation failures return `422` in Laravel's standard shape:

```json
{
  "message": "The selected status is invalid.",
  "errors": { "status": ["The selected status is invalid."] }
}
```

## Route names

Every route is named, so you can `route()` them from your own code.

| Route | Name |
|---|---|
| `GET flows` | `impex.flows.index` |
| `POST flows/{flow}/runs` | `impex.flows.runs.store` |
| `GET runs`, `GET runs/{run}` | `impex.runs.index`, `impex.runs.show` |
| `POST runs/{run}/cancel`, `/retry` | `impex.runs.cancel`, `impex.runs.retry` |
| `GET runs/{run}/steps` | `impex.runs.steps.index` |
| `POST runs/{run}/signals` | `impex.runs.signals.store` |
| `GET`, `POST`, `DELETE runs/{run}/owners…` | `impex.runs.owners.index`, `.store`, `.destroy` |
| `GET messages`, `GET messages/{message}` | `impex.messages.index`, `impex.messages.show` |
| `GET channels`, `POST channels` | `impex.channels.index`, `impex.channels.store` |
| `GET`, `PATCH`, `DELETE channels/{…}` | `impex.channels.show`, `.update`, `.destroy` |
| `POST channels/{channel}/rotate-secret` | `impex.channels.rotate-secret` |
| `POST channels/{channel}` (receive) | `impex.channels.receive` |
| A configured channel's custom `path` | `impex.channels.receive.{name}` |
| `GET streams` | `impex.streams.index` |
| `subscribers…` | `impex.subscribers.index`, `.store`, `.show`, `.update`, `.destroy` |
| `POST subscribers/{subscriber}/subscriptions` | `impex.subscriptions.store` |
| `subscriptions…` | `impex.subscriptions.index`, `.show`, `.update`, `.destroy`, `.ping`, `.export`, `.rotate-secret`, `.subjects`, `.events`, `.deliveries` |
| `subscriber/…` | `impex.subscriber.streams.index`, `impex.subscriber.subscriptions.store`, and `impex.subscriber.subscriptions.*` as above |
| `GET stats` | `impex.stats.show` |
| `GET search` | `impex.search.index` |
| `GET settings-summary` | `impex.settings-summary.show` |
| `GET history` | `impex.history.index` |

The receive routes were renamed with the Channel domain: the generic endpoint
is `impex.channels.receive`, and a custom path is
`impex.channels.receive.{name}` (it was `impex.channels.{name}`), which keeps
`impex.channels.index` and the other operator names free.
