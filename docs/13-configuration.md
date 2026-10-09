# Configuration

Every key in `config/impex.php`.

## `flows`

```php
'flows' => [
    'extract-products' => \App\Flows\ExtractProductsFlow::class,
],
```

String keys set the slug; unkeyed entries derive one from the class name. Always
wins over a package's runtime registration — see [Extending](12-extending.md).

## `schedule`

```php
'schedule' => ['extract-products' => '0 * * * *'],
```

Cron expressions keyed by slug. A `schedule` value on the flow's `impex_flows`
row takes precedence, so the dashboard can reschedule without a deploy.

## `queue`

| Key | Default | Meaning |
|---|---|---|
| `connection` | `null` | Queue connection for engine jobs. `null` uses the app default. |
| `queue` | `null` | Queue name. |

Jobs carry ULIDs only, never payloads, so a message can never approach SQS's
256KB limit however large a run's data is. Subscription jobs have their own
lanes in `subscriptions.queue`.

## `jobs`

```php
'jobs' => [
    'middleware' => [
        '*' => [\App\Queue\TagWithTenant::class],
        \RefactorCircus\Impex\Jobs\DeliverSubscription::class => [
            [\Illuminate\Queue\Middleware\RateLimited::class, 'impex-deliveries'],
        ],
    ],
],
```

| Key | Default | Meaning |
|---|---|---|
| `middleware` | `[]` | Queue middleware for Impex's jobs, by job class; `*` applies to all of them, before a class's own. |

An entry is a middleware class, a list of a class and its constructor
arguments, or a `RefactorCircus\Impex\Contracts\JobMiddlewareFactory` that builds
middleware for the job in hand. Read when a job runs, not when it was queued.
The jobs: `DriveRun`, `ExecuteStep`, `SeedBatch`, `ProcessBatchItem`,
`DetectStream`, `DeliverSubscription` and `ExportSubscription`, all in
`RefactorCircus\Impex\Jobs`. See [Extending](12-extending.md#job-middleware).

## `limits`

| Key | Default | Meaning |
|---|---|---|
| `max_step_seconds` | `840` | The working window for a step. Keep below the queue worker's timeout — 900s on Lambda. |
| `resume_margin_seconds` | `30` | Headroom before that window, at which `shouldYield()` flips. |
| `lease_seconds` | `900` | How long a claimed step is owned before another invocation may reclaim it. **Must exceed `max_step_seconds`.** |
| `lock_seconds` | `120` | TTL of the per-run drive lock. |
| `fan_out_max` | `100` | Item ceiling for `fanOut()`. Above it, use `batch()`. |
| `max_resumptions` | `10000` | How many times one step may checkpoint before failing. |
| `sync_seconds` | `15` | Budget for `Impex::runSync()` and for an API caller passing `wait: true`. |

If `lease_seconds` drops below `max_step_seconds`, a legitimately slow step has
its lease reclaimed while still working, and runs twice.

## `deadlines`

| Key | Default | Meaning |
|---|---|---|
| `run` | `null` | Default seconds before a run passes its deadline and roll backs. |
| `step` | `null` | Default seconds before a step passes its deadline and fails. |

Null means no deadline. Enforced by `impex:tick`, not in-process: a step that
has handed control to an upstream call cannot check a clock. Per-run and
per-step values override these.

## `artifacts`

| Key | Default | Meaning |
|---|---|---|
| `disk` | `null` | Flysystem disk for payloads. Point at S3 on Vapor. `null` uses the app default. |
| `path` | `'impex'` | Path prefix on that disk. |
| `inline_threshold` | `65536` | Bytes above which a payload goes to the disk instead of a column. |

## `cache`

| Key | Default | Meaning |
|---|---|---|
| `store` | `null` | Store backing run locks. Must be **shared and lock-capable** — Redis or DynamoDB on Vapor. |

Lambda shares no memory between invocations, so this is what serialises
concurrent drives of one run. A store that cannot take atomic locks raises an
exception naming it.

## `timers`

| Key | Default | Meaning |
|---|---|---|
| `enabled` | `true` | Whether the package registers `impex:tick` on the schedule. |
| `max_queue_delay` | `900` | Below this, a native queue delay is used instead of a timer row. |
| `claim_seconds` | `300` | How long a sweep's claim is held before the timer becomes claimable again. |
| `batch` | `250` | Timers fired per sweep. |

## `messages`

| Key | Default | Meaning |
|---|---|---|
| `preview_bytes` | `2048` | How much of a text body `body_preview` keeps for list views. Display only: a kept body is whole in its row or on the artifact disk. |
| `max_recorded_bytes` | `16777216` (16MB) | The largest outbound HTTP body `Impex::http()` records whole. A larger or unrewindable one — a streamed feed — is recorded by size alone, so recording never holds a whole feed in memory. |

## `outbound`

Defaults for sending through outbound channels. A channel's own `options` win.

| Key | Default | Meaning |
|---|---|---|
| `timeout` | `10` | HTTP request timeout, seconds. |
| `connect_timeout` | `3` | HTTP connect timeout, seconds. |
| `signer` | `StandardWebhooksSigner::class` | Signs the body of every HTTP send through a channel that has a secret. `null` signs nothing. |
| `guard` | `true` | Refuse endpoints an outside party supplied (owned channels, or `options.guard`) that resolve to private or reserved addresses. Leave it on outside tests. |
| `allow_insecure` | `false` | Permit plain `http://` for guarded endpoints. |

See [Channels and transports](18-channels.md#the-endpoint-guard).

## `mail`

| Key | Default | Meaning |
|---|---|---|
| `channel` | `'mail'` | The ledger channel recorded mail is filed under, when the `impex` mailer does not name its own. |
| `record_unwrapped` | `false` | Also record mail sent through any other mailer, from the `MessageSent` event — successes only, since a failed send never fires it. |

The `impex` mail transport does the recording; configure a mailer with it in
`config/mail.php`. See [Channels and transports](18-channels.md#mail).

## `channel_registry`

| Key | Default | Meaning |
|---|---|---|
| `cache_seconds` | `30` | How long each process holds the stored channels before reading them again. A change shows at once in the process that made it, and in others within this window. |

## `retention`

| Key | Default |
|---|---|
| `completed_runs_days` | `90` |
| `failed_runs_days` | `365` |
| `messages_days` | `90` |
| `artifacts_days` | `365` |
| `events_days` | `30` |
| `deliveries_days` | `30` |

**Artifacts must never expire before the rows referencing them**, or a failed
run loses the payloads you would open it to read. Keep `artifacts_days` at or
above the longest of the others. `impex:prune` deletes in dependency order.

Subscription events are kept for `events_days` whether or not they were
delivered, so a subscriber can replay or catch up on its feed within the window
and no further; the window also bounds who is told when a subject is removed.
`deliveries_days` keeps the per-attempt rows in `impex_deliveries`.

## `authorization`

`true` by default: the JSON API and MCP tools act as the authenticated user —
listing only the runs they own, making them the owner of runs they start, and
checking every call against `impex.policies`. Set it to `false` only for a
trusted operator surface, where the route middleware is the only check. The
inbound channel endpoints are never user-authorized, and the subscriber API is
scoped to the calling subscriber rather than checked against these policies.

## `policies`

The policy the Gate uses for each model, keyed by model class. Point a model at
your own class to replace its policy. See
[Ownership](08-ownership.md#authorizing-the-api).

## `routes`

| Key | Default |
|---|---|
| `enabled` | `true` |
| `prefix` | `'impex'` |
| `middleware` | `['api']` |
| `channel_middleware` | `['api']` |
| `subscriber_middleware` | `['api']` |

Add authentication to `middleware` before exposing these. `channel_middleware`
is the separate stack for the inbound channel receive endpoints, which
authenticate with the channel's signing secret rather than a user, so keep
operator authentication off it and add a throttle of your own.

`subscriber_middleware` is the stack for the subscriber API under
`{prefix}/subscriber`, where vendors and partners manage their own
subscriptions. Put your OAuth middleware here, such as `['api', 'auth:api']`,
or `['api', 'client:impex']` for client-credentials tokens; the subscriber is
then found by the token's OAuth client (`subscriptions.resolver`).

## `mcp`

```php
'mcp' => [
    'web' => ['enabled' => false, 'route' => 'mcp/impex', 'middleware' => []],
    'local' => ['enabled' => false, 'handle' => 'impex'],
],
```

Both ship disabled.

## `cortex`

| Key | Default | Meaning |
|---|---|---|
| `enabled` | `true` | Connect the MCP server and tools to `refactor-circus/cortex` when it is installed. |
| `server` | `'impex'` | The server's name in Cortex. |
| `tools` | `null` | `null` for every tool, or a list of tool names such as `['list-runs-tool', 'show-run-tool']`. |

Cortex is optional; without it these keys do nothing.

## `ui`

```php
'ui' => [
    'enabled' => true, // register Impex with the Atrium dashboard
],
```

Atrium owns the dashboard's path, middleware and authorization gate, so this is
the only setting. Turning it off keeps the JSON API serving.

See [Dashboard](11-dashboard.md).

## `atrium`

```php
'atrium' => [
    'features' => [ImpexSupportFeature::class],
    'show_all' => false,
],
```

| Key | Default | Meaning |
|---|---|---|
| `features` | `[ImpexSupportFeature::class]` | Features that switch Impex in Atrium on and off as a whole. Classes that are not installed are skipped. |
| `show_all` | `false` | Dashboard operators. `true`: everyone past Atrium's gate; a string: those the named Gate ability allows. An operator sees every run, message, count, widget and search result and may use every Impex control, on the Atrium screens only. Ignored with `authorization` off. |

## `streams`

```php
'streams' => [
    \App\Streams\OrderStream::class,
],
```

Stream classes subscribers can follow, each implementing
`RefactorCircus\Impex\Domains\Subscription\Contracts\Stream`. Packages register their own
with `Impex::streams()->register()`. See [Subscriptions](19-subscriptions.md).

## `subscriptions`

```php
'subscriptions' => [
    'queue' => [
        'detect' => ['connection' => null, 'queue' => null],
        'deliver' => ['connection' => null, 'queue' => null],
    ],
    'detection' => ['chunk' => 1000, 'concurrency' => 1, 'lease_seconds' => 300, 'time_budget' => 50],
    'delivery' => [
        'batch' => 100,
        'time_budget' => 50,
        'gzip' => false,
        'breaker_threshold' => 20,
        'backoff' => ['base' => 10, 'max' => 3600, 'jitter' => true],
    ],
    'feed' => ['max_limit' => 1000],
    'exports' => ['disk' => null, 'path' => 'impex/exports'],
    'default_format' => 'slice',
    'body_policy' => 'failures',
    'cache_seconds' => 30,
    'resolver' => OAuthClientResolver::class,
],
```

| Key | Default | Meaning |
|---|---|---|
| `queue.detect.connection`, `queue.detect.queue` | `null` | Where `DetectStream` runs. `null` falls back to `impex.queue`. |
| `queue.deliver.connection`, `queue.deliver.queue` | `null` | Where `DeliverSubscription` and `ExportSubscription` run. Separate, so a big import cannot hold up deliveries behind it. |
| `detection.chunk` | `1000` | Touched subjects compared per chunk — one `snapshots()` call, one transaction. Also the append-stream fan-out chunk. |
| `detection.concurrency` | `1` | Detection jobs per stream, sharing the backlog through leases. |
| `detection.lease_seconds` | `300` | How long a claimed chunk is held before another worker may take it. |
| `detection.time_budget` | `50` | Seconds one detection job keeps claiming chunks before queuing the next. |
| `delivery.batch` | `100` | Events per delivery request. |
| `delivery.time_budget` | `50` | Seconds one delivery job keeps sending batches before queuing the next. |
| `delivery.gzip` | `false` | Create subscription endpoints that send gzip-compressed bodies. |
| `delivery.breaker_threshold` | `20` | Consecutive failures that switch a subscription to `disabled`. |
| `delivery.backoff.base` | `10` | Seconds after the first failure; doubles per consecutive failure. |
| `delivery.backoff.max` | `3600` | The longest wait between attempts. |
| `delivery.backoff.jitter` | `true` | ±20%, so subscriptions failing against one outage do not retry together. |
| `feed.max_limit` | `1000` | The most events one feed page returns. |
| `exports.disk` | `null` | Disk exports are written to. `null` is the default disk. |
| `exports.path` | `'impex/exports'` | Path prefix; files land at `{path}/{subscription}/{ulid}.jsonl`. |
| `default_format` | `'slice'` | Format for a subscription that names none: `thin`, `slice` or `full`. |
| `body_policy` | `'failures'` | Body policy of the endpoint channel each new push subscription gets. Every batch is recorded with its hash either way. |
| `cache_seconds` | `30` | How long subscriptions are cached between detection chunks in a process. |
| `resolver` | `OAuthClientResolver::class` | Maps an authenticated subscriber-API request to its subscriber; a `ResolvesSubscriber`. |

## `channels`

```php
'channels' => [
    'supplier-feed' => [
        'direction' => 'inbound',
        'signing_secret' => env('IMPEX_SUPPLIER_SECRET'),
        'signature_header' => 'X-Signature',
        'signature_validator' => HmacSha256Validator::class,
        'profile' => ProcessEverything::class,
        'idempotency_header' => 'X-Request-Id',
        'flow' => 'extract-products',
        'store_headers' => ['content-type'],
        'path' => null,
    ],
    'carrier-api' => [
        'direction' => 'outbound',
        'transport' => 'http',
        'options' => ['url' => 'https://api.carrier.test/rates', 'timeout' => 5],
        'credentials' => ['signing_secret' => env('CARRIER_SECRET')],
        'body_policy' => 'failures',
        'status' => 'active',
    ],
],
```

| Key | Default | Meaning |
|---|---|---|
| `direction` | `'inbound'` | `inbound` or `outbound`. |
| `transport` | `'http'` | `http`, `mail`, `file`, or one registered with `Impex::transports()->extend()`. |
| `options` | `[]` | Transport settings. http: `url`, `method`, `headers`, `timeout`, `connect_timeout`, `gzip`, `signer`, `guard`. mail: `mailer`, `to`, `subject`. file: `disk`, `path`. Inbound with `StandardWebhooksValidator`: `tolerance_seconds`. |
| `credentials` | `[]` | `signing_secret`, and `secrets`: older secrets still accepted, and still signing, during a rotation. |
| `body_policy` | `'all'` | Which bodies the ledger keeps: `all`, `failures`, `none`. Sizes and hashes are always kept. |
| `status` | `'active'` | `active` or `disabled`. A disabled channel neither sends nor receives. |
| `signing_secret` | `null` | Shorthand for `credentials.signing_secret`. |
| `signature_header` | `'X-Signature'` | |
| `signature_validator` | `HmacSha256Validator::class` | Inbound. Or `StandardWebhooksValidator::class`, or your own. |
| `profile` | `ProcessEverything::class` | Inbound: which requests start a run. |
| `idempotency_header` | `null` | Inbound. |
| `flow` | `null` | Inbound: the flow a request starts. |
| `store_headers` | `[]` | Headers kept in the ledger, both directions. |
| `queue` | `null` | Reserved; not read by the engine. |
| `path` | `null` | Inbound: a custom receive path, default `channels/{name}`. |

A channel stored in `impex_channels` under the same name wins. See
[Channels and transports](18-channels.md) and [The ledger](07-ledger.md).
