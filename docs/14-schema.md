# Schema

All tables are ULID-keyed with hardcoded names. Table names are **not**
configurable: the validation rules on Actions embed literal table names, and a
configurable connection would silently void every foreign key.

## `impex_runs`

One execution of a flow.

| Column | Notes |
|---|---|
| `id` | ulid pk |
| `flow` | registry slug, indexed |
| `flow_class` | recorded for divergence detection |
| `flow_version` | the version the run started under; `$this->version()` branches on it |
| `status` | see [Flows](02-flows.md#statuses) |
| `trigger` | `api` `mcp` `command` `schedule` `channel` `code` `child` |
| `idempotency_key` | **unique per flow**, with `flow`; a repeat returns the original run |
| `input`, `input_artifact_id` | inline below the threshold, on the disk above it |
| `result`, `result_artifact_id` | same |
| `error` | class, message, file, line |
| `tags` | queryable json |
| `parent_run_id`, `parent_sequence` | child workflows |
| `close_policy` | what happens to this child if its parent finishes first |
| `queue_connection`, `queue` | per-run routing |
| `expires_at`, `started_at`, `finished_at` | |

Indexes: `(status, created_at)`, `(flow, status)`, `(parent_run_id)`, and
`unique(flow, idempotency_key)` (`impex_runs_idempotency_unique`). Scoped to
the flow because two channels may legitimately deliver the same key to
different flows; a global unique would make the second a 500. A concurrent
duplicate that loses the insert returns the run that won.

## `impex_run_steps`

The replay log.

| Column | Notes |
|---|---|
| `run_id`, `phase`, `sequence` | **unique together** — the idempotency spine |
| `type` | `action` `rollback` `side_effect` `signal` `fan_out` `batch` `child` `timer` |
| `name` | action class, side-effect key, signal name |
| `status` | `pending` `running` `completed` `failed` `rolled back` `skipped` |
| `input`/`result` + artifact ids | |
| `rollback` | `{action, arguments}`, captured when the forward step is recorded |
| `rolled back` | whether the rollback has run |
| `attempts`, `max_attempts` | |
| `cursor`, `resumptions` | the resume checkpoint and how many times it fired |
| `lease_token`, `leased_until` | claim-before-execute; a lapsed lease is reclaimable |
| `undoes_sequence` | back-reference from a rollback step |
| `unit_id` | groups steps declared in one `unit()` block, so a rollback can find a step's peers and apply the group's policy |

`phase` gives rollback its own sequence space, so rollback steps never
collide with the forward history the replay reads.

## `impex_run_owners`

| Column | Notes |
|---|---|
| `id` | surrogate ulid, so the morph triple is addressable |
| `run_id`, `owner_type`, `owner_id`, `role` | **unique together** |

Sized to 26+191+64+32 chars — 1252 bytes under utf8mb4, inside InnoDB's
3072-byte key limit.

## `impex_signals`

| Column | Notes |
|---|---|
| `run_id`, `name`, `idempotency_key` | **unique together** |
| `payload`, `payload_artifact_id` | |
| `delivered_at`, `consumed_at`, `consumed_sequence` | |

A signal delivered before the run waits for it is held and consumed on arrival.

## `impex_timers`

| Column | Notes |
|---|---|
| `run_id`, `phase`, `sequence` | the step waiting on it |
| `kind` | `sleep` `signal_timeout` `run_deadline` `step_deadline` |
| `wake_at` | indexed |
| `claimed_at`, `claim_token`, `fired_at` | lease-claimed by `impex:tick` |

Why it exists: SQS caps message delay at 15 minutes.

## `impex_messages`

The ledger.

| Column | Notes |
|---|---|
| `run_id`, `step_id` | null for messages received before a run exists |
| `delivery_id` | the subscription delivery that sent it, when one did; indexed |
| `direction` | `inbound` `outbound` |
| `channel`, `transport`, `endpoint`, `method`, `status_code` | |
| `headers` | filtered by the channel's `store_headers` |
| `body` | the whole body, up to the artifact threshold; null when the channel's body policy did not keep it or it went to the disk |
| `body_artifact_id` | a kept body above the threshold |
| `body_preview` | the first `preview_bytes` of a text body |
| `body_encoding` | `utf8`, or `base64` for a binary body |
| `body_sha256` | kept even when the body is not |
| `bytes` | |
| `signature_valid` | inbound only |
| `duration_ms` | |
| `error` | why a crossing failed |
| `channel` + `idempotency_key` | **unique together** |
| `occurred_at` | indexed |

Indexes: `(direction, occurred_at)`, `(channel, occurred_at)`,
`(run_id, occurred_at)`.

Rows are written by a query-builder insert, not Eloquent, so no `MessageModel`
lifecycle events fire for them; `MessageRecorded` does. See
[The ledger](07-ledger.md#how-a-row-is-written).

## `impex_channels`

Channels stored at runtime. Configured channels have no row.

| Column | Notes |
|---|---|
| `name` | **unique**; carried by every ledger row, so never changed |
| `direction` | `inbound` `outbound` |
| `transport` | `http` `mail` `file` or a registered name |
| `status` | `active` `disabled` |
| `credentials` | secrets, `encrypted:array`; hidden from serialization and never rendered |
| `options` | json: transport settings, and an inbound channel's validator, profile, flow and headers |
| `body_policy` | `all` `failures` `none` |
| `owner_type`, `owner_id` | who supplied it — a subscriber, a user; null for the application's own |

Indexes: `(owner_type, owner_id)`, `(direction, status)`.

## Subscription tables

Built for catalogues in the tens of millions. Nothing here grows with catalogue
size times subscriber count: a change is one event row, and each subscriber it
matters to adds one two-column row pointing at it. Payloads are built when they
are sent, never copied per subscriber. See [Subscriptions](19-subscriptions.md).

### `impex_subscribers`

| Column | Notes |
|---|---|
| `name` | |
| `client_id` | **unique**, nullable: the OAuth client it authenticates as |
| `status` | `active` `disabled` |
| `owner_type`, `owner_id` | who manages it on the operator API; indexed together |
| `metadata` | json |

### `impex_subscriptions`

| Column | Notes |
|---|---|
| `subscriber_id` | cascades on delete |
| `stream` | the stream key |
| `channel_id` | the endpoint channel; null for a feed-only subscription; nulled on delete |
| `topics` | a bit mask over the stream's topics |
| `selection` | `all` `filter` `list` |
| `filter` | json |
| `format` | `thin` `slice` `full` or a stream's own |
| `options` | json |
| `status` | `active` `paused` `disabled` |
| `cursor` | the last event delivered; delivery reads forward from it |
| `failures` | consecutive failed deliveries |
| `pending_at` | set when events are waiting |
| `paused_until` | backoff after a failure |
| `disabled_at` | when the breaker tripped |
| `last_delivered_at`, `last_error` | |
| `last_export_path`, `last_export_at` | the latest export file |

Indexes: `(stream, status)`, `(status, pending_at)`.

### `impex_subscription_subjects`

A `list` subscription's subject keys. Primary key
`(subscription_id, subject_key)`, plus an index on `subject_key`.

### `impex_stream_touches`

Subjects a snapshot stream has said changed, not yet looked at. One row per
subject however often it is touched: a burst of saves is one look.

| Column | Notes |
|---|---|
| `stream`, `subject_key` | primary key |
| `revision` | new on every touch |
| `claimed_revision` | the revision a detector claimed; a row touched again while it was being looked at survives the release |
| `lease_token`, `leased_until` | the detector's claim |
| `touched_at` | |

### `impex_subject_states`

What each subject looked like when last compared.

| Column | Notes |
|---|---|
| `stream`, `subject_key` | primary key |
| `hashes` | one 16-hex-character xxh3 per topic, in topic order, then one of the whole snapshot |
| `updated_at` | |

### `impex_events`

The sequence. Its auto-increment `id` is every subscriber's cursor.

| Column | Notes |
|---|---|
| `id` | bigint, increasing |
| `stream`, `subject_key` | indexed together |
| `kind` | `changed` `removed` `appended` |
| `name` | an append event's name, or `entered_scope` / `left_scope` |
| `topics` | bit mask |
| `payload` | an append event's data; snapshot events carry none |
| `batch` | ties a bulk insert's rows to the job that wrote them; indexed |
| `fanned_out_at` | null for an append event not yet fanned out |
| `occurred_at` | indexed; pruned after `retention.events_days` |

### `impex_subscription_events`

Who each event goes to: `(subscription_id, event_id)`, the primary key, plus an
index on `event_id`. Two columns, so fan-out to a hundred subscribers is a
hundred narrow rows.

### `impex_deliveries`

One row per delivery attempt.

| Column | Notes |
|---|---|
| `subscription_id` | cascades on delete |
| `first_event_id`, `last_event_id`, `events` | the range sent |
| `status` | `succeeded` `failed` |
| `message_id` | the ledger row of the request |
| `status_code`, `duration_ms`, `error` | |
| `attempted_at` | indexed; pruned after `retention.deliveries_days` |

## `impex_artifacts`

| Column | Notes |
|---|---|
| `disk`, `path`, `mime`, `bytes`, `checksum` | |
| `kind` | `payload` `result` `image` `document` `other` |
| `run_id`, `step_id`, `message_id` | plain indexed ULIDs, **no foreign keys** |
| `expires_at` | |

Uses `Prunable`, not `MassPrunable`: mass pruning issues a bulk delete without
hydrating models, so `pruning()` never fires and every stored object would be
orphaned on the disk.

**Circular references.** Runs, steps and messages point at artifacts; artifacts
point back. Both directions cannot be constrained, so the artifact side carries
no foreign keys — it is always written first. The `*_artifact_id` columns hold
the real constraints, with `nullOnDelete`.

## `impex_flows`

Runtime overrides only. The registry decides which flows exist.

| Column | Notes |
|---|---|
| `slug` | unique; a row for an unregistered slug is inert |
| `enabled` | nullable; only an explicit `false` disables |
| `schedule` | overrides `impex.schedule` |
| `queue`, `queue_connection` | the queue new runs of the flow are routed to, unless the caller names one (an inbound channel's `queue` does) |
| `defaults` | default arguments for new runs, keyed by `handle()` parameter name, filling only what the caller left out. The stored run input carries the merged arguments by name, so replay never reads this again. Ignored for a flow with a variadic parameter |

## `impex_batches` / `impex_batch_items`

Per-item state for `batch()`, deliberately **outside** the replay log.

`impex_batches`: `run_id`, `step_id`, `source`, `source_arguments`, `action`,
`chunk_size`, `allow_failures`, `max_attempts`, `seeded`, `total`, `succeeded`,
`failed`, `finalized_at`.

`impex_batch_items`: `batch_id` + `item_key` **unique together** — the item-level
equivalent of `unique(run_id, sequence)`. Plus `payload`, `result`, `error`,
`attempts`, `lease_token`, `leased_until`. `attempts` counts every attempt that
started, including one whose worker died, so an item that keeps killing its
worker runs out of attempts rather than looping.

This is what keeps a million-item sweep to a two-step history.
