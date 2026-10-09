# Release Notes

## [Unreleased](https://github.com/jayi/impex/compare/v0.1.0...1.x)

### Added

- **Faster, steadier delivery.** A subscription's next batch is read with two primary-key queries instead of one join: on MySQL the join could be planned from the events table, scanning every event after the cursor — every subscription's — for each batch of one, so a batch's cost grew with the whole stream. Fan-out's history lookup is split the same way. A delivered batch's record and cursor commit in one transaction, and a delivery lock that cannot be released is reported instead of failing a finished job.

- **Channel screens.** Create, change, rotate the secret of, and delete stored channels from Atrium; configured channels are shown read-only.
- **A paused flow is paused everywhere.** `Impex::run()` refuses a disabled flow from any trigger, not only the API; an inbound channel bound to one answers `503` with `Retry-After`.
- **Flow overrides route and default runs.** `impex_flows.queue` and `queue_connection` now apply to new runs (an inbound channel's own `queue` wins), and `defaults` fills the `handle()` parameters a caller left out, by name — a nightly sweep's batch size, say, retuned without a deploy.
- **Failed batch items back off** before their next attempt, as failed steps do.
- **Faster pruning.** Runs prune in bulk (their children cascade); artifacts prune a thousand at a time with one bulk file delete per disk. New indexes on `impex_run_steps.expires_at` and `impex_runs (status, finished_at)`.
- **A benchmark** in the workbench: `vendor/bin/testbench impex:bench`.
- **Job middleware from config.** `impex.jobs.middleware` gives Impex's queued jobs queue middleware by job class, with `*` for all of them: a middleware class, `[class, ...constructor arguments]`, or a `JayI\Impex\Contracts\JobMiddlewareFactory` that builds middleware for the job in hand (a `WithoutOverlapping` keyed by subscription, say).
- **Batch item leases are reclaimed.** `impex:tick` takes back items whose worker died mid-attempt once their lease lapses, counting the attempt, so an item that keeps killing its worker fails instead of blocking its batch. `ProcessBatchItem` implements Laravel's `Interruptible`: when the worker times it out (Laravel 13.34+, `pcntl`), it gives the item up at once rather than waiting for the lease.
- **Outbound channels.** Channels now go both ways and can be stored at runtime (`impex_channels`) as well as configured; a stored channel wins over a configured one of the same name. `Impex::send($channel, $message)` sends through a channel's transport — `http`, `mail`, `file`, or one registered with `Impex::transports()->extend()` — and returns a `Receipt`; a failed send is a failed receipt, not an exception. HTTP sends through a channel with a secret are signed (`StandardWebhooksSigner` by default, every secret signing during a rotation). Endpoints an outside party supplied are held to the `EndpointGuard`: https only, no private or reserved addresses, the request pinned to the address that was checked. Channel CRUD and secret rotation over the API (`/impex/channels`) and MCP.
- **Mail recording.** The `impex` mail transport wraps a real mailer and records every mail it sends, failures included; `impex.mail.record_unwrapped` records other mailers' mail from `MessageSent`.
- **Subscriptions.** Streams (`Impex::streams()`), subscribers, subscriptions, change detection, fan-out, batched signed push delivery with backoff and a circuit breaker, a cursor feed, full exports, and a subscriber API under `/impex/subscriber` authenticated by the application's OAuth middleware (`impex.routes.subscriber_middleware`). Snapshot streams are touched and diffed per topic; append streams publish discrete events. Operator API, MCP tools and an Atrium **Subscriptions** screen. `impex:tick` queues detection and due deliveries; `impex:prune` prunes subscription events (`retention.events_days`) and deliveries (`retention.deliveries_days`).
- `StandardWebhooksValidator` for inbound channels, and every secret in a channel's `credentials.secrets` accepted beside its `signing_secret`.
- Ledger rows keep a body's sha256 (`body_sha256`), its encoding, and the subscription delivery that sent it (`delivery_id`); `impex_messages` gains an index on `(channel, occurred_at)`.
- The package's section in Atrium's sidebar rail has its own icon (`arrows-right-left`) and a fixed place in the rail.
- An **Audit log** link in the package's sidebar group, opening its own audit log in Atrium (`/atrium/history/impex`), shown while an audit log (jayi/keen) is installed and to those who may read the package's history.

### Breaking

- Requires `laravel/framework` `^13.34`, for jobs being told when a worker times them out.
- The Channels nav item and screens follow `ChannelPolicy` (`viewAny`), like the other screens, instead of showing to anyone signed in. The bundled policy still allows everyone.
- `RunModel` prunes with `MassPrunable`: no model events fire for pruned runs.
- Channel classes move to a new `Channel` domain: `JayI\Impex\Domains\Message\{Contracts\ChannelProfile, Contracts\SignatureValidator, Data\ChannelConfig, Services\ChannelRegistry, Support\HmacSha256Validator, Support\ProcessEverything, Exceptions\UnknownChannelException, Actions\ListChannelsAction}` → `JayI\Impex\Domains\Channel\...`. The inbound receive controller is `Message\Http\Controllers\ReceiveMessageController`, and a configured channel's custom-path route is named `impex.channels.receive.{name}`. An outbound or disabled channel no longer receives (`404`).
- `GET /impex/channels` lists outbound channels too, with each one's `transport`, `status`, `body_policy` and `options`; filter with `?direction=inbound`.
- The ledger is written with a query-builder insert: `MessageModel` lifecycle events no longer fire for recorded messages (`MessageRecorded` still does). Bodies are kept whole in the new `body` column up to the artifact threshold instead of only as a 2KB preview, and the `MessageModel::body()` relation is renamed `bodyArtifact()`.
- Outbound HTTP through `Impex::http()` is recorded when it fails without a response too, with the request headers the channel names in `store_headers`.
- A run's idempotency key is unique per flow (`(flow, idempotency_key)`), not globally, and a concurrent duplicate returns the first run instead of failing.
- New tables: `impex_channels`, `impex_subscribers`, `impex_subscriptions`, `impex_subscription_subjects`, `impex_stream_touches`, `impex_subject_states`, `impex_events`, `impex_subscription_events`, `impex_deliveries`.
- Impex ships no stylesheet and no Blade components; Atrium owns them all. `resources/css/atrium.css` and its `Atrium::css()` registration are gone (every utility the screens use is in Atrium's stylesheet), the `<x-impex::status>` component is now the `impex::ui.partials.status-dot` partial (`@include('impex::ui.partials.status-dot', ['status' => $run->status])`), and the `impex::ui.partials.status` partial is replaced by `<x-atrium::flash />`. Republish `impex-views` if you customised them.
- `JayI\Impex\Atrium\ScreenAccess::allowsFor()` is gone: `allows($ability, $subject, $arguments = [], ?Request $request = null)` takes the request instead, and delegates to Atrium's shared `ScreenAccess::allows('impex', ...)` once operators are let through.
- Impex now stands on [jayi/foundation](https://github.com/jayjfletcher/Foundation), the shared runtime of the jayi suite, and its local copies are gone. `ImpexServiceProvider` extends `PackageServiceProvider` and registers Impex with the suite's `PackageRegistry` (key `impex`); policies, the MCP server, Cortex and the Atrium plugin are wired through its helpers, reading the same `impex.*` config keys as before. Old → new:
  - `JayI\Impex\Contracts\ActionStartingEvent`, `ActionFinishedEvent`, `ModelLifecycleEvent` → `JayI\Foundation\Contracts\...` (listen to these to hear every package of the suite)
  - `JayI\Impex\Support\Models\Concerns\DispatchesModelEvents` → `JayI\Foundation\Models\Concerns\DispatchesModelEvents`
  - `JayI\Impex\Http\Request` → `JayI\Foundation\Http\Requests\Request`
  - `JayI\Impex\Mcp\Request` → `JayI\Foundation\Mcp\Requests\Request` (calls now run inside the `mcp` surface)
  - `JayI\Impex\Mcp\Tool` → `JayI\Foundation\Mcp\Tool`
  - `JayI\Impex\Support\Authorizer` → `JayI\Foundation\Auth\Authorizer`, built with `Authorizer::for(app(PackageRegistry::class)->get('impex'))` rather than resolved from the container
  - `JayI\Impex\Cortex\CortexIntegration` → `JayI\Foundation\Cortex\CortexIntegration`, built with `CortexIntegration::for($package)`; agent tool calls are now marked with the `cortex` surface
  - `JayI\Impex\Support\ServiceProvider` → `JayI\Foundation\Support\ServiceProvider` (the inbound channel route group moves into `MessageServiceProvider`)
  - `ImpexServer` extends `JayI\Foundation\Mcp\Server`, which serves the Cortex instructions override; `ImpexException` extends `JayI\Foundation\Exceptions\PackageException`; `Support\Policies\Policy` extends `JayI\Foundation\Policies\Policy`.
- Every `ImpexException` thrown during an HTTP request now answers `409` with its message as JSON, as `CannotSignalTerminalRunException` already did. A disabled flow, for example, was a `500`.

- The package is reorganised into domain modules (`src/Domains/Run`, `Flow`, `Signal`, `Batch`, `Message`, `Artifact`), mirroring the mono application's layout. Classes move namespaces and the models gain a `Model` suffix (`Run` → `RunModel`, ...); there are no aliases for the old class names, so update imports, `impex.policies` keys and any `impex.channels` profile or validator classes. The flow DSL base classes (`Flow`, `ResumableAction`) and the builders move to `Domains\Flow\Support`, and the engine and its collaborators to `Domains\Run\Services` - rebind them by their new names. Config keys, route names and paths, MCP tool names, publish tags, views, translations, tables and model event class names are unchanged. Package-wide pieces keep their names: `ImpexServiceProvider`, the `Impex` class and facade, `ImpexPlugin`, `Badges`, the event contracts, `Http\Request`, `Mcp\Request`, `Mcp\Tool`, `Mcp\ImpexServer`, `ImpexException`, `Support\Locks`, `Testing\Flows`, `CortexIntegration` and `PruneCommand`. The queued jobs (`DriveRun`, `ExecuteStep`, `ProcessBatchItem`, `SeedBatch`) keep their `JayI\Impex\Jobs` names, so jobs already on a queue still run. Each model keeps its old class name as its morph alias, so values stored under it (a run owner's `owner_type`, an audit subject) still resolve, and `ImpexSupportFeature` keeps its Pennant stored name (`JayI\Impex\Features\ImpexSupportFeature`). The JSON API routes now load from each domain (`routes/impex.php` is gone). References to Atrium, Cortex and PennantPlus follow their domain-module renames. Old → new:
  - `JayI\Impex\Access\Authorizer` → `JayI\Impex\Support\Authorizer`
  - `JayI\Impex\Actions\AttachRunOwnerAction` → `JayI\Impex\Domains\Run\Actions\AttachRunOwnerAction`
  - `JayI\Impex\Actions\CancelRunAction` → `JayI\Impex\Domains\Run\Actions\CancelRunAction`
  - `JayI\Impex\Actions\DetachRunOwnerAction` → `JayI\Impex\Domains\Run\Actions\DetachRunOwnerAction`
  - `JayI\Impex\Actions\ListChannelsAction` → `JayI\Impex\Domains\Message\Actions\ListChannelsAction`
  - `JayI\Impex\Actions\ListFlowsAction` → `JayI\Impex\Domains\Flow\Actions\ListFlowsAction`
  - `JayI\Impex\Actions\ListMessagesAction` → `JayI\Impex\Domains\Message\Actions\ListMessagesAction`
  - `JayI\Impex\Actions\ListRunOwnersAction` → `JayI\Impex\Domains\Run\Actions\ListRunOwnersAction`
  - `JayI\Impex\Actions\ListRunStepsAction` → `JayI\Impex\Domains\Run\Actions\ListRunStepsAction`
  - `JayI\Impex\Actions\ListRunsAction` → `JayI\Impex\Domains\Run\Actions\ListRunsAction`
  - `JayI\Impex\Actions\RetryRunAction` → `JayI\Impex\Domains\Run\Actions\RetryRunAction`
  - `JayI\Impex\Actions\RunFlowAction` → `JayI\Impex\Domains\Flow\Actions\RunFlowAction`
  - `JayI\Impex\Actions\ShowMessageAction` → `JayI\Impex\Domains\Message\Actions\ShowMessageAction`
  - `JayI\Impex\Actions\ShowRunAction` → `JayI\Impex\Domains\Run\Actions\ShowRunAction`
  - `JayI\Impex\Actions\SignalRunAction` → `JayI\Impex\Domains\Signal\Actions\SignalRunAction`
  - `JayI\Impex\Channels\ChannelConfig` → `JayI\Impex\Domains\Message\Data\ChannelConfig`
  - `JayI\Impex\Channels\ChannelRegistry` → `JayI\Impex\Domains\Message\Services\ChannelRegistry`
  - `JayI\Impex\Channels\Profiles\ProcessEverything` → `JayI\Impex\Domains\Message\Support\ProcessEverything`
  - `JayI\Impex\Channels\Validators\HmacSha256Validator` → `JayI\Impex\Domains\Message\Support\HmacSha256Validator`
  - `JayI\Impex\Console\Commands\RunFlowCommand` → `JayI\Impex\Domains\Flow\Console\Commands\RunFlowCommand`
  - `JayI\Impex\Console\Commands\SignalCommand` → `JayI\Impex\Domains\Signal\Console\Commands\SignalCommand`
  - `JayI\Impex\Console\Commands\TickCommand` → `JayI\Impex\Domains\Run\Console\Commands\TickCommand`
  - `JayI\Impex\Console\FlowArguments` → `JayI\Impex\Domains\Flow\Support\FlowArguments`
  - `JayI\Impex\Contracts\BatchSource` → `JayI\Impex\Domains\Batch\Contracts\BatchSource`
  - `JayI\Impex\Contracts\ChannelProfile` → `JayI\Impex\Domains\Message\Contracts\ChannelProfile`
  - `JayI\Impex\Contracts\Resumable` → `JayI\Impex\Domains\Flow\Contracts\Resumable`
  - `JayI\Impex\Contracts\RollbackStrategy` → `JayI\Impex\Domains\Run\Contracts\RollbackStrategy`
  - `JayI\Impex\Contracts\SignatureValidator` → `JayI\Impex\Domains\Message\Contracts\SignatureValidator`
  - `JayI\Impex\Enums\ArtifactKind` → `JayI\Impex\Domains\Artifact\Enums\ArtifactKind`
  - `JayI\Impex\Enums\ChildClosePolicy` → `JayI\Impex\Domains\Run\Enums\ChildClosePolicy`
  - `JayI\Impex\Enums\Direction` → `JayI\Impex\Domains\Message\Enums\Direction`
  - `JayI\Impex\Enums\ParallelFailure` → `JayI\Impex\Domains\Run\Enums\ParallelFailure`
  - `JayI\Impex\Enums\RollbackFailure` → `JayI\Impex\Domains\Run\Enums\RollbackFailure`
  - `JayI\Impex\Enums\RunStatus` → `JayI\Impex\Domains\Run\Enums\RunStatus`
  - `JayI\Impex\Enums\RunTrigger` → `JayI\Impex\Domains\Run\Enums\RunTrigger`
  - `JayI\Impex\Enums\StepPhase` → `JayI\Impex\Domains\Run\Enums\StepPhase`
  - `JayI\Impex\Enums\StepStatus` → `JayI\Impex\Domains\Run\Enums\StepStatus`
  - `JayI\Impex\Enums\StepType` → `JayI\Impex\Domains\Run\Enums\StepType`
  - `JayI\Impex\Enums\TimerKind` → `JayI\Impex\Domains\Signal\Enums\TimerKind`
  - `JayI\Impex\Events\Action\ChannelsListedActionEvent` → `JayI\Impex\Domains\Message\Events\ChannelsListedActionEvent`
  - `JayI\Impex\Events\Action\ChannelsListingActionEvent` → `JayI\Impex\Domains\Message\Events\ChannelsListingActionEvent`
  - `JayI\Impex\Events\Action\FlowRanActionEvent` → `JayI\Impex\Domains\Flow\Events\FlowRanActionEvent`
  - `JayI\Impex\Events\Action\FlowRunningActionEvent` → `JayI\Impex\Domains\Flow\Events\FlowRunningActionEvent`
  - `JayI\Impex\Events\Action\FlowsListedActionEvent` → `JayI\Impex\Domains\Flow\Events\FlowsListedActionEvent`
  - `JayI\Impex\Events\Action\FlowsListingActionEvent` → `JayI\Impex\Domains\Flow\Events\FlowsListingActionEvent`
  - `JayI\Impex\Events\Action\MessageShowingActionEvent` → `JayI\Impex\Domains\Message\Events\MessageShowingActionEvent`
  - `JayI\Impex\Events\Action\MessageShownActionEvent` → `JayI\Impex\Domains\Message\Events\MessageShownActionEvent`
  - `JayI\Impex\Events\Action\MessagesListedActionEvent` → `JayI\Impex\Domains\Message\Events\MessagesListedActionEvent`
  - `JayI\Impex\Events\Action\MessagesListingActionEvent` → `JayI\Impex\Domains\Message\Events\MessagesListingActionEvent`
  - `JayI\Impex\Events\Action\RunCancelledActionEvent` → `JayI\Impex\Domains\Run\Events\RunCancelledActionEvent`
  - `JayI\Impex\Events\Action\RunCancellingActionEvent` → `JayI\Impex\Domains\Run\Events\RunCancellingActionEvent`
  - `JayI\Impex\Events\Action\RunOwnerAttachedActionEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerAttachedActionEvent`
  - `JayI\Impex\Events\Action\RunOwnerAttachingActionEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerAttachingActionEvent`
  - `JayI\Impex\Events\Action\RunOwnerDetachedActionEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerDetachedActionEvent`
  - `JayI\Impex\Events\Action\RunOwnerDetachingActionEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerDetachingActionEvent`
  - `JayI\Impex\Events\Action\RunOwnersListedActionEvent` → `JayI\Impex\Domains\Run\Events\RunOwnersListedActionEvent`
  - `JayI\Impex\Events\Action\RunOwnersListingActionEvent` → `JayI\Impex\Domains\Run\Events\RunOwnersListingActionEvent`
  - `JayI\Impex\Events\Action\RunRetriedActionEvent` → `JayI\Impex\Domains\Run\Events\RunRetriedActionEvent`
  - `JayI\Impex\Events\Action\RunRetryingActionEvent` → `JayI\Impex\Domains\Run\Events\RunRetryingActionEvent`
  - `JayI\Impex\Events\Action\RunShowingActionEvent` → `JayI\Impex\Domains\Run\Events\RunShowingActionEvent`
  - `JayI\Impex\Events\Action\RunShownActionEvent` → `JayI\Impex\Domains\Run\Events\RunShownActionEvent`
  - `JayI\Impex\Events\Action\RunSignalledActionEvent` → `JayI\Impex\Domains\Signal\Events\RunSignalledActionEvent`
  - `JayI\Impex\Events\Action\RunSignallingActionEvent` → `JayI\Impex\Domains\Signal\Events\RunSignallingActionEvent`
  - `JayI\Impex\Events\Action\RunStepsListedActionEvent` → `JayI\Impex\Domains\Run\Events\RunStepsListedActionEvent`
  - `JayI\Impex\Events\Action\RunStepsListingActionEvent` → `JayI\Impex\Domains\Run\Events\RunStepsListingActionEvent`
  - `JayI\Impex\Events\Action\RunsListedActionEvent` → `JayI\Impex\Domains\Run\Events\RunsListedActionEvent`
  - `JayI\Impex\Events\Action\RunsListingActionEvent` → `JayI\Impex\Domains\Run\Events\RunsListingActionEvent`
  - `JayI\Impex\Events\MessageRecorded` → `JayI\Impex\Domains\Message\Events\MessageRecorded`
  - `JayI\Impex\Events\Model\ArtifactCreatedEvent` → `JayI\Impex\Domains\Artifact\Events\ArtifactCreatedEvent`
  - `JayI\Impex\Events\Model\ArtifactCreatingEvent` → `JayI\Impex\Domains\Artifact\Events\ArtifactCreatingEvent`
  - `JayI\Impex\Events\Model\ArtifactDeletedEvent` → `JayI\Impex\Domains\Artifact\Events\ArtifactDeletedEvent`
  - `JayI\Impex\Events\Model\ArtifactDeletingEvent` → `JayI\Impex\Domains\Artifact\Events\ArtifactDeletingEvent`
  - `JayI\Impex\Events\Model\ArtifactReplicatingEvent` → `JayI\Impex\Domains\Artifact\Events\ArtifactReplicatingEvent`
  - `JayI\Impex\Events\Model\ArtifactRetrievedEvent` → `JayI\Impex\Domains\Artifact\Events\ArtifactRetrievedEvent`
  - `JayI\Impex\Events\Model\ArtifactSavedEvent` → `JayI\Impex\Domains\Artifact\Events\ArtifactSavedEvent`
  - `JayI\Impex\Events\Model\ArtifactSavingEvent` → `JayI\Impex\Domains\Artifact\Events\ArtifactSavingEvent`
  - `JayI\Impex\Events\Model\ArtifactUpdatedEvent` → `JayI\Impex\Domains\Artifact\Events\ArtifactUpdatedEvent`
  - `JayI\Impex\Events\Model\ArtifactUpdatingEvent` → `JayI\Impex\Domains\Artifact\Events\ArtifactUpdatingEvent`
  - `JayI\Impex\Events\Model\BatchCreatedEvent` → `JayI\Impex\Domains\Batch\Events\BatchCreatedEvent`
  - `JayI\Impex\Events\Model\BatchCreatingEvent` → `JayI\Impex\Domains\Batch\Events\BatchCreatingEvent`
  - `JayI\Impex\Events\Model\BatchDeletedEvent` → `JayI\Impex\Domains\Batch\Events\BatchDeletedEvent`
  - `JayI\Impex\Events\Model\BatchDeletingEvent` → `JayI\Impex\Domains\Batch\Events\BatchDeletingEvent`
  - `JayI\Impex\Events\Model\BatchItemCreatedEvent` → `JayI\Impex\Domains\Batch\Events\BatchItemCreatedEvent`
  - `JayI\Impex\Events\Model\BatchItemCreatingEvent` → `JayI\Impex\Domains\Batch\Events\BatchItemCreatingEvent`
  - `JayI\Impex\Events\Model\BatchItemDeletedEvent` → `JayI\Impex\Domains\Batch\Events\BatchItemDeletedEvent`
  - `JayI\Impex\Events\Model\BatchItemDeletingEvent` → `JayI\Impex\Domains\Batch\Events\BatchItemDeletingEvent`
  - `JayI\Impex\Events\Model\BatchItemReplicatingEvent` → `JayI\Impex\Domains\Batch\Events\BatchItemReplicatingEvent`
  - `JayI\Impex\Events\Model\BatchItemRetrievedEvent` → `JayI\Impex\Domains\Batch\Events\BatchItemRetrievedEvent`
  - `JayI\Impex\Events\Model\BatchItemSavedEvent` → `JayI\Impex\Domains\Batch\Events\BatchItemSavedEvent`
  - `JayI\Impex\Events\Model\BatchItemSavingEvent` → `JayI\Impex\Domains\Batch\Events\BatchItemSavingEvent`
  - `JayI\Impex\Events\Model\BatchItemUpdatedEvent` → `JayI\Impex\Domains\Batch\Events\BatchItemUpdatedEvent`
  - `JayI\Impex\Events\Model\BatchItemUpdatingEvent` → `JayI\Impex\Domains\Batch\Events\BatchItemUpdatingEvent`
  - `JayI\Impex\Events\Model\BatchReplicatingEvent` → `JayI\Impex\Domains\Batch\Events\BatchReplicatingEvent`
  - `JayI\Impex\Events\Model\BatchRetrievedEvent` → `JayI\Impex\Domains\Batch\Events\BatchRetrievedEvent`
  - `JayI\Impex\Events\Model\BatchSavedEvent` → `JayI\Impex\Domains\Batch\Events\BatchSavedEvent`
  - `JayI\Impex\Events\Model\BatchSavingEvent` → `JayI\Impex\Domains\Batch\Events\BatchSavingEvent`
  - `JayI\Impex\Events\Model\BatchUpdatedEvent` → `JayI\Impex\Domains\Batch\Events\BatchUpdatedEvent`
  - `JayI\Impex\Events\Model\BatchUpdatingEvent` → `JayI\Impex\Domains\Batch\Events\BatchUpdatingEvent`
  - `JayI\Impex\Events\Model\FlowOverrideCreatedEvent` → `JayI\Impex\Domains\Flow\Events\FlowOverrideCreatedEvent`
  - `JayI\Impex\Events\Model\FlowOverrideCreatingEvent` → `JayI\Impex\Domains\Flow\Events\FlowOverrideCreatingEvent`
  - `JayI\Impex\Events\Model\FlowOverrideDeletedEvent` → `JayI\Impex\Domains\Flow\Events\FlowOverrideDeletedEvent`
  - `JayI\Impex\Events\Model\FlowOverrideDeletingEvent` → `JayI\Impex\Domains\Flow\Events\FlowOverrideDeletingEvent`
  - `JayI\Impex\Events\Model\FlowOverrideReplicatingEvent` → `JayI\Impex\Domains\Flow\Events\FlowOverrideReplicatingEvent`
  - `JayI\Impex\Events\Model\FlowOverrideRetrievedEvent` → `JayI\Impex\Domains\Flow\Events\FlowOverrideRetrievedEvent`
  - `JayI\Impex\Events\Model\FlowOverrideSavedEvent` → `JayI\Impex\Domains\Flow\Events\FlowOverrideSavedEvent`
  - `JayI\Impex\Events\Model\FlowOverrideSavingEvent` → `JayI\Impex\Domains\Flow\Events\FlowOverrideSavingEvent`
  - `JayI\Impex\Events\Model\FlowOverrideUpdatedEvent` → `JayI\Impex\Domains\Flow\Events\FlowOverrideUpdatedEvent`
  - `JayI\Impex\Events\Model\FlowOverrideUpdatingEvent` → `JayI\Impex\Domains\Flow\Events\FlowOverrideUpdatingEvent`
  - `JayI\Impex\Events\Model\MessageCreatedEvent` → `JayI\Impex\Domains\Message\Events\MessageCreatedEvent`
  - `JayI\Impex\Events\Model\MessageCreatingEvent` → `JayI\Impex\Domains\Message\Events\MessageCreatingEvent`
  - `JayI\Impex\Events\Model\MessageDeletedEvent` → `JayI\Impex\Domains\Message\Events\MessageDeletedEvent`
  - `JayI\Impex\Events\Model\MessageDeletingEvent` → `JayI\Impex\Domains\Message\Events\MessageDeletingEvent`
  - `JayI\Impex\Events\Model\MessageReplicatingEvent` → `JayI\Impex\Domains\Message\Events\MessageReplicatingEvent`
  - `JayI\Impex\Events\Model\MessageRetrievedEvent` → `JayI\Impex\Domains\Message\Events\MessageRetrievedEvent`
  - `JayI\Impex\Events\Model\MessageSavedEvent` → `JayI\Impex\Domains\Message\Events\MessageSavedEvent`
  - `JayI\Impex\Events\Model\MessageSavingEvent` → `JayI\Impex\Domains\Message\Events\MessageSavingEvent`
  - `JayI\Impex\Events\Model\MessageUpdatedEvent` → `JayI\Impex\Domains\Message\Events\MessageUpdatedEvent`
  - `JayI\Impex\Events\Model\MessageUpdatingEvent` → `JayI\Impex\Domains\Message\Events\MessageUpdatingEvent`
  - `JayI\Impex\Events\Model\RunCreatedEvent` → `JayI\Impex\Domains\Run\Events\RunCreatedEvent`
  - `JayI\Impex\Events\Model\RunCreatingEvent` → `JayI\Impex\Domains\Run\Events\RunCreatingEvent`
  - `JayI\Impex\Events\Model\RunDeletedEvent` → `JayI\Impex\Domains\Run\Events\RunDeletedEvent`
  - `JayI\Impex\Events\Model\RunDeletingEvent` → `JayI\Impex\Domains\Run\Events\RunDeletingEvent`
  - `JayI\Impex\Events\Model\RunOwnerCreatedEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerCreatedEvent`
  - `JayI\Impex\Events\Model\RunOwnerCreatingEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerCreatingEvent`
  - `JayI\Impex\Events\Model\RunOwnerDeletedEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerDeletedEvent`
  - `JayI\Impex\Events\Model\RunOwnerDeletingEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerDeletingEvent`
  - `JayI\Impex\Events\Model\RunOwnerReplicatingEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerReplicatingEvent`
  - `JayI\Impex\Events\Model\RunOwnerRetrievedEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerRetrievedEvent`
  - `JayI\Impex\Events\Model\RunOwnerSavedEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerSavedEvent`
  - `JayI\Impex\Events\Model\RunOwnerSavingEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerSavingEvent`
  - `JayI\Impex\Events\Model\RunOwnerUpdatedEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerUpdatedEvent`
  - `JayI\Impex\Events\Model\RunOwnerUpdatingEvent` → `JayI\Impex\Domains\Run\Events\RunOwnerUpdatingEvent`
  - `JayI\Impex\Events\Model\RunReplicatingEvent` → `JayI\Impex\Domains\Run\Events\RunReplicatingEvent`
  - `JayI\Impex\Events\Model\RunRetrievedEvent` → `JayI\Impex\Domains\Run\Events\RunRetrievedEvent`
  - `JayI\Impex\Events\Model\RunSavedEvent` → `JayI\Impex\Domains\Run\Events\RunSavedEvent`
  - `JayI\Impex\Events\Model\RunSavingEvent` → `JayI\Impex\Domains\Run\Events\RunSavingEvent`
  - `JayI\Impex\Events\Model\RunStepCreatedEvent` → `JayI\Impex\Domains\Run\Events\RunStepCreatedEvent`
  - `JayI\Impex\Events\Model\RunStepCreatingEvent` → `JayI\Impex\Domains\Run\Events\RunStepCreatingEvent`
  - `JayI\Impex\Events\Model\RunStepDeletedEvent` → `JayI\Impex\Domains\Run\Events\RunStepDeletedEvent`
  - `JayI\Impex\Events\Model\RunStepDeletingEvent` → `JayI\Impex\Domains\Run\Events\RunStepDeletingEvent`
  - `JayI\Impex\Events\Model\RunStepReplicatingEvent` → `JayI\Impex\Domains\Run\Events\RunStepReplicatingEvent`
  - `JayI\Impex\Events\Model\RunStepRetrievedEvent` → `JayI\Impex\Domains\Run\Events\RunStepRetrievedEvent`
  - `JayI\Impex\Events\Model\RunStepSavedEvent` → `JayI\Impex\Domains\Run\Events\RunStepSavedEvent`
  - `JayI\Impex\Events\Model\RunStepSavingEvent` → `JayI\Impex\Domains\Run\Events\RunStepSavingEvent`
  - `JayI\Impex\Events\Model\RunStepUpdatedEvent` → `JayI\Impex\Domains\Run\Events\RunStepUpdatedEvent`
  - `JayI\Impex\Events\Model\RunStepUpdatingEvent` → `JayI\Impex\Domains\Run\Events\RunStepUpdatingEvent`
  - `JayI\Impex\Events\Model\RunUpdatedEvent` → `JayI\Impex\Domains\Run\Events\RunUpdatedEvent`
  - `JayI\Impex\Events\Model\RunUpdatingEvent` → `JayI\Impex\Domains\Run\Events\RunUpdatingEvent`
  - `JayI\Impex\Events\Model\SignalCreatedEvent` → `JayI\Impex\Domains\Signal\Events\SignalCreatedEvent`
  - `JayI\Impex\Events\Model\SignalCreatingEvent` → `JayI\Impex\Domains\Signal\Events\SignalCreatingEvent`
  - `JayI\Impex\Events\Model\SignalDeletedEvent` → `JayI\Impex\Domains\Signal\Events\SignalDeletedEvent`
  - `JayI\Impex\Events\Model\SignalDeletingEvent` → `JayI\Impex\Domains\Signal\Events\SignalDeletingEvent`
  - `JayI\Impex\Events\Model\SignalReplicatingEvent` → `JayI\Impex\Domains\Signal\Events\SignalReplicatingEvent`
  - `JayI\Impex\Events\Model\SignalRetrievedEvent` → `JayI\Impex\Domains\Signal\Events\SignalRetrievedEvent`
  - `JayI\Impex\Events\Model\SignalSavedEvent` → `JayI\Impex\Domains\Signal\Events\SignalSavedEvent`
  - `JayI\Impex\Events\Model\SignalSavingEvent` → `JayI\Impex\Domains\Signal\Events\SignalSavingEvent`
  - `JayI\Impex\Events\Model\SignalUpdatedEvent` → `JayI\Impex\Domains\Signal\Events\SignalUpdatedEvent`
  - `JayI\Impex\Events\Model\SignalUpdatingEvent` → `JayI\Impex\Domains\Signal\Events\SignalUpdatingEvent`
  - `JayI\Impex\Events\Model\TimerCreatedEvent` → `JayI\Impex\Domains\Signal\Events\TimerCreatedEvent`
  - `JayI\Impex\Events\Model\TimerCreatingEvent` → `JayI\Impex\Domains\Signal\Events\TimerCreatingEvent`
  - `JayI\Impex\Events\Model\TimerDeletedEvent` → `JayI\Impex\Domains\Signal\Events\TimerDeletedEvent`
  - `JayI\Impex\Events\Model\TimerDeletingEvent` → `JayI\Impex\Domains\Signal\Events\TimerDeletingEvent`
  - `JayI\Impex\Events\Model\TimerReplicatingEvent` → `JayI\Impex\Domains\Signal\Events\TimerReplicatingEvent`
  - `JayI\Impex\Events\Model\TimerRetrievedEvent` → `JayI\Impex\Domains\Signal\Events\TimerRetrievedEvent`
  - `JayI\Impex\Events\Model\TimerSavedEvent` → `JayI\Impex\Domains\Signal\Events\TimerSavedEvent`
  - `JayI\Impex\Events\Model\TimerSavingEvent` → `JayI\Impex\Domains\Signal\Events\TimerSavingEvent`
  - `JayI\Impex\Events\Model\TimerUpdatedEvent` → `JayI\Impex\Domains\Signal\Events\TimerUpdatedEvent`
  - `JayI\Impex\Events\Model\TimerUpdatingEvent` → `JayI\Impex\Domains\Signal\Events\TimerUpdatingEvent`
  - `JayI\Impex\Events\RunCompleted` → `JayI\Impex\Domains\Run\Events\RunCompleted`
  - `JayI\Impex\Events\RunFailed` → `JayI\Impex\Domains\Run\Events\RunFailed`
  - `JayI\Impex\Events\RunStarted` → `JayI\Impex\Domains\Run\Events\RunStarted`
  - `JayI\Impex\Events\StepCompleted` → `JayI\Impex\Domains\Run\Events\StepCompleted`
  - `JayI\Impex\Events\StepFailed` → `JayI\Impex\Domains\Run\Events\StepFailed`
  - `JayI\Impex\Exceptions\BatchFailedException` → `JayI\Impex\Domains\Batch\Exceptions\BatchFailedException`
  - `JayI\Impex\Exceptions\CannotSignalTerminalRunException` → `JayI\Impex\Domains\Signal\Exceptions\CannotSignalTerminalRunException`
  - `JayI\Impex\Exceptions\DeadlineExceededException` → `JayI\Impex\Domains\Run\Exceptions\DeadlineExceededException`
  - `JayI\Impex\Exceptions\DisabledFlowException` → `JayI\Impex\Domains\Flow\Exceptions\DisabledFlowException`
  - `JayI\Impex\Exceptions\FanOutTooLargeException` → `JayI\Impex\Domains\Flow\Exceptions\FanOutTooLargeException`
  - `JayI\Impex\Exceptions\FlowCollisionException` → `JayI\Impex\Domains\Flow\Exceptions\FlowCollisionException`
  - `JayI\Impex\Exceptions\FlowVersionMismatchException` → `JayI\Impex\Domains\Flow\Exceptions\FlowVersionMismatchException`
  - `JayI\Impex\Exceptions\HistoryMismatchException` → `JayI\Impex\Domains\Run\Exceptions\HistoryMismatchException`
  - `JayI\Impex\Exceptions\PayloadException` → `JayI\Impex\Domains\Artifact\Exceptions\PayloadException`
  - `JayI\Impex\Exceptions\SignalTimeoutException` → `JayI\Impex\Domains\Signal\Exceptions\SignalTimeoutException`
  - `JayI\Impex\Exceptions\StalledStepException` → `JayI\Impex\Domains\Run\Exceptions\StalledStepException`
  - `JayI\Impex\Exceptions\StepFailedException` → `JayI\Impex\Domains\Run\Exceptions\StepFailedException`
  - `JayI\Impex\Exceptions\UnknownChannelException` → `JayI\Impex\Domains\Message\Exceptions\UnknownChannelException`
  - `JayI\Impex\Exceptions\UnknownFlowException` → `JayI\Impex\Domains\Flow\Exceptions\UnknownFlowException`
  - `JayI\Impex\Features\ImpexSupportFeature` → `JayI\Impex\Atrium\Features\ImpexSupportFeature`
  - `JayI\Impex\Flows\Builders\ActionBuilder` → `JayI\Impex\Domains\Flow\Support\ActionBuilder`
  - `JayI\Impex\Flows\Builders\BatchBuilder` → `JayI\Impex\Domains\Flow\Support\BatchBuilder`
  - `JayI\Impex\Flows\Builders\ChildBuilder` → `JayI\Impex\Domains\Flow\Support\ChildBuilder`
  - `JayI\Impex\Flows\Builders\FanOutBuilder` → `JayI\Impex\Domains\Flow\Support\FanOutBuilder`
  - `JayI\Impex\Flows\Builders\ParallelBuilder` → `JayI\Impex\Domains\Flow\Support\ParallelBuilder`
  - `JayI\Impex\Flows\Builders\SignalBuilder` → `JayI\Impex\Domains\Flow\Support\SignalBuilder`
  - `JayI\Impex\Flows\Builders\UnitBuilder` → `JayI\Impex\Domains\Flow\Support\UnitBuilder`
  - `JayI\Impex\Flows\Concerns\CanResume` → `JayI\Impex\Domains\Flow\Concerns\CanResume`
  - `JayI\Impex\Flows\Flow` → `JayI\Impex\Domains\Flow\Support\Flow`
  - `JayI\Impex\Flows\FlowRegistry` → `JayI\Impex\Domains\Flow\Services\FlowRegistry`
  - `JayI\Impex\Flows\ResumableAction` → `JayI\Impex\Domains\Flow\Support\ResumableAction`
  - `JayI\Impex\Http\Controllers\ChannelController` → `JayI\Impex\Domains\Message\Http\Controllers\ChannelController`
  - `JayI\Impex\Http\Controllers\ChannelIndexController` → `JayI\Impex\Domains\Message\Http\Controllers\ChannelIndexController`
  - `JayI\Impex\Http\Controllers\FlowController` → `JayI\Impex\Domains\Flow\Http\Controllers\FlowController`
  - `JayI\Impex\Http\Controllers\MessageController` → `JayI\Impex\Domains\Message\Http\Controllers\MessageController`
  - `JayI\Impex\Http\Controllers\RunController` → `JayI\Impex\Domains\Run\Http\Controllers\RunController`
  - `JayI\Impex\Http\Controllers\RunOwnerController` → `JayI\Impex\Domains\Run\Http\Controllers\RunOwnerController`
  - `JayI\Impex\Http\Controllers\RunSignalController` → `JayI\Impex\Domains\Signal\Http\Controllers\RunSignalController`
  - `JayI\Impex\Http\Controllers\RunStepController` → `JayI\Impex\Domains\Run\Http\Controllers\RunStepController`
  - `JayI\Impex\Http\Requests\CancelRunRequest` → `JayI\Impex\Domains\Run\Http\Requests\CancelRunRequest`
  - `JayI\Impex\Http\Requests\DeleteRunOwnerRequest` → `JayI\Impex\Domains\Run\Http\Requests\DeleteRunOwnerRequest`
  - `JayI\Impex\Http\Requests\IndexChannelsRequest` → `JayI\Impex\Domains\Message\Http\Requests\IndexChannelsRequest`
  - `JayI\Impex\Http\Requests\IndexFlowsRequest` → `JayI\Impex\Domains\Flow\Http\Requests\IndexFlowsRequest`
  - `JayI\Impex\Http\Requests\IndexMessagesRequest` → `JayI\Impex\Domains\Message\Http\Requests\IndexMessagesRequest`
  - `JayI\Impex\Http\Requests\IndexRunOwnersRequest` → `JayI\Impex\Domains\Run\Http\Requests\IndexRunOwnersRequest`
  - `JayI\Impex\Http\Requests\IndexRunStepsRequest` → `JayI\Impex\Domains\Run\Http\Requests\IndexRunStepsRequest`
  - `JayI\Impex\Http\Requests\IndexRunsRequest` → `JayI\Impex\Domains\Run\Http\Requests\IndexRunsRequest`
  - `JayI\Impex\Http\Requests\RetryRunRequest` → `JayI\Impex\Domains\Run\Http\Requests\RetryRunRequest`
  - `JayI\Impex\Http\Requests\RunRequest` → `JayI\Impex\Domains\Run\Http\Requests\RunRequest`
  - `JayI\Impex\Http\Requests\ShowMessageRequest` → `JayI\Impex\Domains\Message\Http\Requests\ShowMessageRequest`
  - `JayI\Impex\Http\Requests\ShowRunRequest` → `JayI\Impex\Domains\Run\Http\Requests\ShowRunRequest`
  - `JayI\Impex\Http\Requests\StoreFlowRunRequest` → `JayI\Impex\Domains\Flow\Http\Requests\StoreFlowRunRequest`
  - `JayI\Impex\Http\Requests\StoreRunOwnerRequest` → `JayI\Impex\Domains\Run\Http\Requests\StoreRunOwnerRequest`
  - `JayI\Impex\Http\Requests\StoreRunSignalRequest` → `JayI\Impex\Domains\Signal\Http\Requests\StoreRunSignalRequest`
  - `JayI\Impex\Http\Resources\MessageResource` → `JayI\Impex\Domains\Message\Resources\MessageResource`
  - `JayI\Impex\Http\Resources\RunOwnerResource` → `JayI\Impex\Domains\Run\Resources\RunOwnerResource`
  - `JayI\Impex\Http\Resources\RunResource` → `JayI\Impex\Domains\Run\Resources\RunResource`
  - `JayI\Impex\Http\Resources\RunStepResource` → `JayI\Impex\Domains\Run\Resources\RunStepResource`
  - `JayI\Impex\Http\Resources\SignalResource` → `JayI\Impex\Domains\Signal\Resources\SignalResource`
  - `JayI\Impex\Http\Ui\ChannelUiController` → `JayI\Impex\Atrium\Http\Controllers\ChannelUiController`
  - `JayI\Impex\Http\Ui\Concerns\AuthorizesScreens` → `JayI\Impex\Atrium\Http\Controllers\Concerns\AuthorizesScreens`
  - `JayI\Impex\Http\Ui\FlowUiController` → `JayI\Impex\Atrium\Http\Controllers\FlowUiController`
  - `JayI\Impex\Http\Ui\MessageUiController` → `JayI\Impex\Atrium\Http\Controllers\MessageUiController`
  - `JayI\Impex\Http\Ui\RunUiController` → `JayI\Impex\Atrium\Http\Controllers\RunUiController`
  - `JayI\Impex\Http\Ui\ScreenAccess` → `JayI\Impex\Atrium\ScreenAccess`
  - `JayI\Impex\Mcp\Requests\AttachRunOwnerMcpRequest` → `JayI\Impex\Domains\Run\Mcp\Requests\AttachRunOwnerMcpRequest`
  - `JayI\Impex\Mcp\Requests\CancelRunMcpRequest` → `JayI\Impex\Domains\Run\Mcp\Requests\CancelRunMcpRequest`
  - `JayI\Impex\Mcp\Requests\DetachRunOwnerMcpRequest` → `JayI\Impex\Domains\Run\Mcp\Requests\DetachRunOwnerMcpRequest`
  - `JayI\Impex\Mcp\Requests\ListChannelsMcpRequest` → `JayI\Impex\Domains\Message\Mcp\Requests\ListChannelsMcpRequest`
  - `JayI\Impex\Mcp\Requests\ListFlowsMcpRequest` → `JayI\Impex\Domains\Flow\Mcp\Requests\ListFlowsMcpRequest`
  - `JayI\Impex\Mcp\Requests\ListMessagesMcpRequest` → `JayI\Impex\Domains\Message\Mcp\Requests\ListMessagesMcpRequest`
  - `JayI\Impex\Mcp\Requests\ListRunOwnersMcpRequest` → `JayI\Impex\Domains\Run\Mcp\Requests\ListRunOwnersMcpRequest`
  - `JayI\Impex\Mcp\Requests\ListRunStepsMcpRequest` → `JayI\Impex\Domains\Run\Mcp\Requests\ListRunStepsMcpRequest`
  - `JayI\Impex\Mcp\Requests\ListRunsMcpRequest` → `JayI\Impex\Domains\Run\Mcp\Requests\ListRunsMcpRequest`
  - `JayI\Impex\Mcp\Requests\RetryRunMcpRequest` → `JayI\Impex\Domains\Run\Mcp\Requests\RetryRunMcpRequest`
  - `JayI\Impex\Mcp\Requests\RunFlowMcpRequest` → `JayI\Impex\Domains\Flow\Mcp\Requests\RunFlowMcpRequest`
  - `JayI\Impex\Mcp\Requests\RunRequest` → `JayI\Impex\Domains\Run\Mcp\Requests\RunRequest`
  - `JayI\Impex\Mcp\Requests\ShowMessageMcpRequest` → `JayI\Impex\Domains\Message\Mcp\Requests\ShowMessageMcpRequest`
  - `JayI\Impex\Mcp\Requests\ShowRunMcpRequest` → `JayI\Impex\Domains\Run\Mcp\Requests\ShowRunMcpRequest`
  - `JayI\Impex\Mcp\Requests\SignalRunMcpRequest` → `JayI\Impex\Domains\Signal\Mcp\Requests\SignalRunMcpRequest`
  - `JayI\Impex\Mcp\Tools\AttachRunOwnerTool` → `JayI\Impex\Domains\Run\Mcp\Tools\AttachRunOwnerTool`
  - `JayI\Impex\Mcp\Tools\CancelRunTool` → `JayI\Impex\Domains\Run\Mcp\Tools\CancelRunTool`
  - `JayI\Impex\Mcp\Tools\DetachRunOwnerTool` → `JayI\Impex\Domains\Run\Mcp\Tools\DetachRunOwnerTool`
  - `JayI\Impex\Mcp\Tools\ListChannelsTool` → `JayI\Impex\Domains\Message\Mcp\Tools\ListChannelsTool`
  - `JayI\Impex\Mcp\Tools\ListFlowsTool` → `JayI\Impex\Domains\Flow\Mcp\Tools\ListFlowsTool`
  - `JayI\Impex\Mcp\Tools\ListMessagesTool` → `JayI\Impex\Domains\Message\Mcp\Tools\ListMessagesTool`
  - `JayI\Impex\Mcp\Tools\ListRunOwnersTool` → `JayI\Impex\Domains\Run\Mcp\Tools\ListRunOwnersTool`
  - `JayI\Impex\Mcp\Tools\ListRunStepsTool` → `JayI\Impex\Domains\Run\Mcp\Tools\ListRunStepsTool`
  - `JayI\Impex\Mcp\Tools\ListRunsTool` → `JayI\Impex\Domains\Run\Mcp\Tools\ListRunsTool`
  - `JayI\Impex\Mcp\Tools\RetryRunTool` → `JayI\Impex\Domains\Run\Mcp\Tools\RetryRunTool`
  - `JayI\Impex\Mcp\Tools\RunFlowTool` → `JayI\Impex\Domains\Flow\Mcp\Tools\RunFlowTool`
  - `JayI\Impex\Mcp\Tools\ShowMessageTool` → `JayI\Impex\Domains\Message\Mcp\Tools\ShowMessageTool`
  - `JayI\Impex\Mcp\Tools\ShowRunTool` → `JayI\Impex\Domains\Run\Mcp\Tools\ShowRunTool`
  - `JayI\Impex\Mcp\Tools\SignalRunTool` → `JayI\Impex\Domains\Signal\Mcp\Tools\SignalRunTool`
  - `JayI\Impex\Models\Artifact` → `JayI\Impex\Domains\Artifact\Models\ArtifactModel`
  - `JayI\Impex\Models\Batch` → `JayI\Impex\Domains\Batch\Models\BatchModel`
  - `JayI\Impex\Models\BatchItem` → `JayI\Impex\Domains\Batch\Models\BatchItemModel`
  - `JayI\Impex\Models\Concerns\DispatchesModelEvents` → `JayI\Impex\Support\Models\Concerns\DispatchesModelEvents`
  - `JayI\Impex\Models\FlowOverride` → `JayI\Impex\Domains\Flow\Models\FlowOverrideModel`
  - `JayI\Impex\Models\Message` → `JayI\Impex\Domains\Message\Models\MessageModel`
  - `JayI\Impex\Models\Run` → `JayI\Impex\Domains\Run\Models\RunModel`
  - `JayI\Impex\Models\RunOwner` → `JayI\Impex\Domains\Run\Models\RunOwnerModel`
  - `JayI\Impex\Models\RunStep` → `JayI\Impex\Domains\Run\Models\RunStepModel`
  - `JayI\Impex\Models\Signal` → `JayI\Impex\Domains\Signal\Models\SignalModel`
  - `JayI\Impex\Models\Timer` → `JayI\Impex\Domains\Signal\Models\TimerModel`
  - `JayI\Impex\Policies\ArtifactPolicy` → `JayI\Impex\Domains\Artifact\Policies\ArtifactPolicy`
  - `JayI\Impex\Policies\BatchItemPolicy` → `JayI\Impex\Domains\Batch\Policies\BatchItemPolicy`
  - `JayI\Impex\Policies\BatchPolicy` → `JayI\Impex\Domains\Batch\Policies\BatchPolicy`
  - `JayI\Impex\Policies\FlowOverridePolicy` → `JayI\Impex\Domains\Flow\Policies\FlowOverridePolicy`
  - `JayI\Impex\Policies\MessagePolicy` → `JayI\Impex\Domains\Message\Policies\MessagePolicy`
  - `JayI\Impex\Policies\Policy` → `JayI\Impex\Support\Policies\Policy`
  - `JayI\Impex\Policies\RunOwnerPolicy` → `JayI\Impex\Domains\Run\Policies\RunOwnerPolicy`
  - `JayI\Impex\Policies\RunPolicy` → `JayI\Impex\Domains\Run\Policies\RunPolicy`
  - `JayI\Impex\Policies\RunStepPolicy` → `JayI\Impex\Domains\Run\Policies\RunStepPolicy`
  - `JayI\Impex\Policies\SignalPolicy` → `JayI\Impex\Domains\Signal\Policies\SignalPolicy`
  - `JayI\Impex\Policies\TimerPolicy` → `JayI\Impex\Domains\Signal\Policies\TimerPolicy`
  - `JayI\Impex\Runtime\BatchChunk` → `JayI\Impex\Domains\Batch\Data\BatchChunk`
  - `JayI\Impex\Runtime\BatchChunkItem` → `JayI\Impex\Domains\Batch\Data\BatchChunkItem`
  - `JayI\Impex\Runtime\BatchRunner` → `JayI\Impex\Domains\Batch\Services\BatchRunner`
  - `JayI\Impex\Runtime\Children` → `JayI\Impex\Domains\Run\Services\Children`
  - `JayI\Impex\Runtime\Context` → `JayI\Impex\Domains\Run\Support\Context`
  - `JayI\Impex\Runtime\Engine` → `JayI\Impex\Domains\Run\Services\Engine`
  - `JayI\Impex\Runtime\EngineOptions` → `JayI\Impex\Domains\Run\Services\EngineOptions`
  - `JayI\Impex\Runtime\Failure` → `JayI\Impex\Domains\Run\Support\Failure`
  - `JayI\Impex\Runtime\JobRouter` → `JayI\Impex\Domains\Run\Services\JobRouter`
  - `JayI\Impex\Runtime\Resume` → `JayI\Impex\Domains\Run\Data\Resume`
  - `JayI\Impex\Runtime\Rollbacks` → `JayI\Impex\Domains\Run\Services\Rollbacks`
  - `JayI\Impex\Runtime\RunHandle` → `JayI\Impex\Domains\Run\Support\RunHandle`
  - `JayI\Impex\Runtime\RunQuery` → `JayI\Impex\Domains\Run\Support\RunQuery`
  - `JayI\Impex\Runtime\StepDeadline` → `JayI\Impex\Domains\Run\Data\StepDeadline`
  - `JayI\Impex\Runtime\StepDescriptor` → `JayI\Impex\Domains\Run\Data\StepDescriptor`
  - `JayI\Impex\Runtime\StepWriter` → `JayI\Impex\Domains\Run\Services\StepWriter`
  - `JayI\Impex\Runtime\Suspended` → `JayI\Impex\Domains\Run\Support\Suspended`
  - `JayI\Impex\Runtime\SweepReport` → `JayI\Impex\Domains\Run\Data\SweepReport`
  - `JayI\Impex\Runtime\Sweeper` → `JayI\Impex\Domains\Run\Services\Sweeper`
  - `JayI\Impex\Runtime\Waits` → `JayI\Impex\Domains\Signal\Services\Waits`
  - `JayI\Impex\Support\MessageRecorder` → `JayI\Impex\Domains\Message\Services\MessageRecorder`
  - `JayI\Impex\Support\OutboundRecorder` → `JayI\Impex\Domains\Message\Services\OutboundRecorder`
  - `JayI\Impex\Support\PayloadStore` → `JayI\Impex\Domains\Artifact\Services\PayloadStore`

### Added

- With an audit log (jayi/keen) installed, the run and message pages show that record's history and the runs page the whole of Impex's, through `<x-atrium::audit-trail source="impex" />`. Nothing renders without one; run steps stay out of it.
- The run, message and settings details use `x-atrium::description-list`.
- A test asserts `AtriumStyles::missingClasses()` and `inlineStyles()` are empty for Impex's views.
- `GET impex/history` (`impex.history.index`) and the `list-impex-history-tool` MCP tool list Impex's audit entries, newest first, once an audit log ([jayi/keen](https://github.com/jayjfletcher/Keen)) is installed. Until then the route answers `404` and the tool explains that none is installed.

- Atrium screens follow Atrium's screen conventions (needs Atrium f5eb488 or later): every action is an icon button (`<x-atrium::icon-button>`), run, step, flow and channel/message signature states are status dots (the `impex::ui.partials.status-dot` partial, with `data-status`), and every Impex nav item has a Heroicons icon. `Badges::forStatus()`, `forFlow()` and `forSignature()` decide every colour; `info` is kept for pending (`pending`, and a run `waiting` on a signal).
- The dashboard asks the same policies as the JSON API and MCP tools: nav items, widgets, search, cards and buttons are shown only when their action would be allowed, through `JayI\Impex\Http\Ui\ScreenAccess` and the `@impexCan` Blade conditional.
- `ImpexSupportFeature` (needs `jayi/pennantplus`) and `impex.atrium.features`: turning the feature off globally hides Impex in Atrium and 404s its pages. Classes that are not installed are skipped.

- `impex.atrium.show_all` (default `false`): dashboard operators. `true` makes everyone past Atrium's gate an operator, a string names a Gate ability that does. On the Atrium screens only, an operator's lists, nav badge, widgets and search cover every run and message (including unowned scheduled and channel runs) and every Impex control is allowed to them; the JSON API and MCP tools are unaffected. `ScreenAccess::viewer()`, `operator()` and `allowsFor()` expose the decision.

### Changed

- `ImpexPlugin::features()` uses Atrium's `featuresFromConfig('impex.atrium.features')`, and the plugin's `key()` and `label()` come from Atrium's base derivation (`impex`, `Impex`).
- With `impex.authorization` on, the Atrium screens now authorize every page and action against `impex.policies` (403 when refused), list and count only the runs the user owns (and their messages) as the API does, and make the user who starts a flow from the dashboard its owner. Previously every Atrium user could see and act on every run. Turn `impex.authorization` off, or register your own policies, for an operator dashboard.
- Status colours: running is now `primary` and pending/waiting `info`; inbound messages are `neutral` rather than `info`.

### Fixed

- The channels screen read a `signed` key the action never returns, so every channel showed as unsigned; it now reads `verifies_signatures`.

- Cortex integration: when `jayi/cortex` is installed, the MCP server registers with it as `impex` and every tool joins its tool registry (tagged `impex`), so agents can use them. Published instruction and tool description overrides are served to MCP clients and agents. Configured under `impex.cortex`; Cortex stays optional.
- `ImpexServer::TOOLS`, the server's tool catalog as a flat list.
- Model events: every model fires a class-based event for each Eloquent hook (`RunCreatingEvent`, `RunStepCreatedEvent`, ...), mapped by the `DispatchesModelEvents` trait.
- Action events: every action fires a start event before its work and a finish event after commit, on success (`FlowRunningActionEvent` / `FlowRanActionEvent`, ...).
- `ModelLifecycleEvent`, `ActionStartingEvent` and `ActionFinishedEvent` contracts, to listen to a whole family at once.
- Policies for every model (`RunPolicy`, `RunStepPolicy`, `RunOwnerPolicy`, `SignalPolicy`, `TimerPolicy`, `BatchPolicy`, `BatchItemPolicy`, `MessagePolicy`, `ArtifactPolicy`, `FlowOverridePolicy`), registered with the Gate from the new `impex.policies` config. A run's owners may do anything with it; child models defer to their run through the Gate, so replacing the run policy flows down.
- `impex.authorization` (on by default): every HTTP endpoint and MCP tool acts as the authenticated user, lists only the runs they own and those runs' messages, attaches them as `owner` of runs they start, and checks each call against the model's policy. Inbound channel endpoints stay signature-authenticated.
- `ListRunOwnersAction`, with `RunOwnersListingActionEvent` / `RunOwnersListedActionEvent`; the run-owner endpoint and tool now go through it.
- `ListRunsAction` and `ListMessagesAction` take an optional `$viewer`, and `RunFlowAction` an optional `$owner`; their action events carry them.
- Replay engine: deterministic `handle()` replay, lease-before-execute step
  claiming, rollback, `parallel()`, `sideEffect()`, signals and timers
- Resume protocol (`ResumableAction`) so a step can span more invocations than
  the platform's execution ceiling allows, checkpointing before it is killed
- `fanOut()` with a collection fingerprint, optional `keyBy()`, and a
  configurable cap; `batch()` for unbounded work, one history step whatever the
  item count
- Artifact offload for payloads above the inline threshold
- Flow registry with `impex_flows` runtime overrides for enable/disable and
  schedule
- Messages ledger with inbound channels (signature validation, profiles,
  idempotency) and an outbound HTTP recorder
- Polymorphic run owners, with no user/team/customer tables shipped
- HTTP API over the Action + `persist()` layering
- Commands: `impex:tick`, `impex:run`, `impex:prune`
- MCP server with fourteen tools at parity with the HTTP API, enforced by an
  arch test with a declared-exceptions list
- Vue 3 dashboard, prebuilt into `public/`, with session, token, oauth (PKCE)
  and custom auth modes
- Bundled Boost skill with determinism and serverless references
- Full documentation set under `docs/`

- Signal builder: `signal($name)->timeoutAfter()->default()->orFail()->wait()`
- `Impex::signalIfRunning()`, the `signalable()` scope, and `impex:signal`

- Child workflows: `child()` with `closePolicy()`, `detached()` and `withTags()`
- Versioning: a `VERSION` constant per flow, `$this->version()` to branch on it,
  and a guard that refuses a slug repointed at a different class
- Deadlines on runs and steps, enforced by `impex:tick`
- `Impex::runSync()`, and `wait: true` on the trigger endpoint and MCP tool
- `unit()` groups with `onRollbackFailure()` and `rollbackTogether()`
- `optionalAction()`
- `Impex::query()` with `handles()`, and `Impex::handle()`
- `JayI\Impex\Testing\Flows` assertion helpers

### Changed

- Requires `laravel/framework` instead of `illuminate/support`, since the package uses form requests, events, queues and jobs from the framework.
- Renamed the rollback vocabulary away from saga jargon: `saga()` is now
  `unit()`, `compensateWith()` is `undoWith()`, `CompensationFailure` is
  `RollbackFailure` (with `Halt` in place of `Stop`), the compensation phase is
  the rollback phase, and `RunStatus::Compensating` is `RollingBack`.
- Broke the engine into collaborators resolved from the container —
  `EngineOptions`, `JobRouter`, `StepWriter`, `Rollbacks`, `Children`, `Waits`
  and `Sweeper` — so an application can replace one without forking. `Engine`
  drops from 1,282 lines to ~720 and is now a facade over them.
- `RollbackStrategy` is a contract, so unwind order and policy are swappable.
- `impex:tick` is presentation only; the passes live in `Sweeper` and return a
  `SweepReport` you can call from a job or a health check.
- `EngineOptions` validates that `lease_seconds` exceeds `max_step_seconds`,
  which previously would have surfaced as a slow step running twice.

### Removed

- The TypeScript SDK (`sdk/`), its npm workspace and `sdk:generate`/`sdk:build` scripts, and the `dedoc/scramble` dev dependency that exported its OpenAPI spec.
- The scaffold's `impex:placeholder` command and placeholder translation file.
- The empty `impex-assets` publish tag: the dashboard's assets belong to Atrium (`atrium-assets`).
- The `impex.queue.after_commit` and `impex.artifacts.stream_threshold` config keys, which nothing read.

### Fixed

- `config/impex.php` now lists `limits.resume_margin_seconds`, `limits.max_resumptions` and `timers.enabled`, which the engine already read with those defaults.
- The MCP server instructions and docs name the tools as they are served (`run-flow-tool`, ...), behind `search_tools` / `execute_tools`.
- Signalling a finished run now throws `CannotSignalTerminalRunException`
  (409 over HTTP) instead of silently writing a row nothing would consume.
- A timed out signal wait is recorded as skipped rather than completed with
  null, so a genuine null payload is distinguishable from nobody answering.
- `expiresAt()` was recorded but never enforced, so a step deadline silently did
  nothing. Deadlines are now swept by `impex:tick`.
- `flow_version` was never written, so the divergence detection the docs
  described did not exist.
- `impex.limits.sync_seconds` was configuration with no code behind it.
- A failed rollback re-selected the same target on the next drive, looping
  the rollback forever. It now halts or skips according to the group's policy.

- Flow registry precedence no longer depends on boot order. Application config
  always wins over a package's runtime registration; previously whichever
  happened to be read first won.
- Two packages registering the same flow slug now raises
  `FlowCollisionException` instead of the second silently shadowing the first.


## [v0.1.0](https://github.com/jayi/impex/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
