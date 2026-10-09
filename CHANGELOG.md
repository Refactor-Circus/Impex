# Release Notes

## [Unreleased](https://github.com/Refactor-Circus/Impex/commits/main)

### Breaking

- Moved to the Refactor Circus organisation: the package is now `refactor-circus/impex` with the PHP namespace `RefactorCircus\Impex` (it was `jayi/impex` and `JayI\Impex`). Update `composer.json` requirements and `use` statements. Old class names are not kept as aliases, so stored values written under them - polymorphic `*_type` columns, audit subjects, Pennant feature names - need updating to the new names.

### Added

- **Faster, steadier delivery.** A subscription's next batch is read with two primary-key queries instead of one join: on MySQL the join could be planned from the events table, scanning every event after the cursor — every subscription's — for each batch of one, so a batch's cost grew with the whole stream. Fan-out's history lookup is split the same way. A delivered batch's record and cursor commit in one transaction, and a delivery lock that cannot be released is reported instead of failing a finished job.

- **Channel screens.** Create, change, rotate the secret of, and delete stored channels from Atrium; configured channels are shown read-only.
- **A paused flow is paused everywhere.** `Impex::run()` refuses a disabled flow from any trigger, not only the API; an inbound channel bound to one answers `503` with `Retry-After`.
- **Flow overrides route and default runs.** `impex_flows.queue` and `queue_connection` now apply to new runs (an inbound channel's own `queue` wins), and `defaults` fills the `handle()` parameters a caller left out, by name — a nightly sweep's batch size, say, retuned without a deploy.
- **Failed batch items back off** before their next attempt, as failed steps do.
- **Faster pruning.** Runs prune in bulk (their children cascade); artifacts prune a thousand at a time with one bulk file delete per disk. New indexes on `impex_run_steps.expires_at` and `impex_runs (status, finished_at)`.
- **A benchmark** in the workbench: `vendor/bin/testbench impex:bench`.
- **Job middleware from config.** `impex.jobs.middleware` gives Impex's queued jobs queue middleware by job class, with `*` for all of them: a middleware class, `[class, ...constructor arguments]`, or a `RefactorCircus\Impex\Contracts\JobMiddlewareFactory` that builds middleware for the job in hand (a `WithoutOverlapping` keyed by subscription, say).
- **Batch item leases are reclaimed.** `impex:tick` takes back items whose worker died mid-attempt once their lease lapses, counting the attempt, so an item that keeps killing its worker fails instead of blocking its batch. `ProcessBatchItem` implements Laravel's `Interruptible`: when the worker times it out (Laravel 13.34+, `pcntl`), it gives the item up at once rather than waiting for the lease.
- **Outbound channels.** Channels now go both ways and can be stored at runtime (`impex_channels`) as well as configured; a stored channel wins over a configured one of the same name. `Impex::send($channel, $message)` sends through a channel's transport — `http`, `mail`, `file`, or one registered with `Impex::transports()->extend()` — and returns a `Receipt`; a failed send is a failed receipt, not an exception. HTTP sends through a channel with a secret are signed (`StandardWebhooksSigner` by default, every secret signing during a rotation). Endpoints an outside party supplied are held to the `EndpointGuard`: https only, no private or reserved addresses, the request pinned to the address that was checked. Channel CRUD and secret rotation over the API (`/impex/channels`) and MCP.
- **Mail recording.** The `impex` mail transport wraps a real mailer and records every mail it sends, failures included; `impex.mail.record_unwrapped` records other mailers' mail from `MessageSent`.
- **Subscriptions.** Streams (`Impex::streams()`), subscribers, subscriptions, change detection, fan-out, batched signed push delivery with backoff and a circuit breaker, a cursor feed, full exports, and a subscriber API under `/impex/subscriber` authenticated by the application's OAuth middleware (`impex.routes.subscriber_middleware`). Snapshot streams are touched and diffed per topic; append streams publish discrete events. Operator API, MCP tools and an Atrium **Subscriptions** screen. `impex:tick` queues detection and due deliveries; `impex:prune` prunes subscription events (`retention.events_days`) and deliveries (`retention.deliveries_days`).
- `StandardWebhooksValidator` for inbound channels, and every secret in a channel's `credentials.secrets` accepted beside its `signing_secret`.
- Ledger rows keep a body's sha256 (`body_sha256`), its encoding, and the subscription delivery that sent it (`delivery_id`); `impex_messages` gains an index on `(channel, occurred_at)`.
- The package's section in Atrium's sidebar rail has its own icon (`arrows-right-left`) and a fixed place in the rail.
- An **Audit log** link in the package's sidebar group, opening its own audit log in Atrium (`/atrium/history/impex`), shown while an audit log (refactor-circus/keen) is installed and to those who may read the package's history.

### Breaking

- Requires `laravel/framework` `^13.34`, for jobs being told when a worker times them out.
- The Channels nav item and screens follow `ChannelPolicy` (`viewAny`), like the other screens, instead of showing to anyone signed in. The bundled policy still allows everyone.
- `RunModel` prunes with `MassPrunable`: no model events fire for pruned runs.
- Channel classes move to a new `Channel` domain: `RefactorCircus\Impex\Domains\Message\{Contracts\ChannelProfile, Contracts\SignatureValidator, Data\ChannelConfig, Services\ChannelRegistry, Support\HmacSha256Validator, Support\ProcessEverything, Exceptions\UnknownChannelException, Actions\ListChannelsAction}` → `RefactorCircus\Impex\Domains\Channel\...`. The inbound receive controller is `Message\Http\Controllers\ReceiveMessageController`, and a configured channel's custom-path route is named `impex.channels.receive.{name}`. An outbound or disabled channel no longer receives (`404`).
- `GET /impex/channels` lists outbound channels too, with each one's `transport`, `status`, `body_policy` and `options`; filter with `?direction=inbound`.
- The ledger is written with a query-builder insert: `MessageModel` lifecycle events no longer fire for recorded messages (`MessageRecorded` still does). Bodies are kept whole in the new `body` column up to the artifact threshold instead of only as a 2KB preview, and the `MessageModel::body()` relation is renamed `bodyArtifact()`.
- Outbound HTTP through `Impex::http()` is recorded when it fails without a response too, with the request headers the channel names in `store_headers`.
- A run's idempotency key is unique per flow (`(flow, idempotency_key)`), not globally, and a concurrent duplicate returns the first run instead of failing.
- New tables: `impex_channels`, `impex_subscribers`, `impex_subscriptions`, `impex_subscription_subjects`, `impex_stream_touches`, `impex_subject_states`, `impex_events`, `impex_subscription_events`, `impex_deliveries`.
- Impex ships no stylesheet and no Blade components; Atrium owns them all. `resources/css/atrium.css` and its `Atrium::css()` registration are gone (every utility the screens use is in Atrium's stylesheet), the `<x-impex::status>` component is now the `impex::ui.partials.status-dot` partial (`@include('impex::ui.partials.status-dot', ['status' => $run->status])`), and the `impex::ui.partials.status` partial is replaced by `<x-atrium::flash />`. Republish `impex-views` if you customised them.
- `RefactorCircus\Impex\Atrium\ScreenAccess::allowsFor()` is gone: `allows($ability, $subject, $arguments = [], ?Request $request = null)` takes the request instead, and delegates to Atrium's shared `ScreenAccess::allows('impex', ...)` once operators are let through.
- Impex now stands on [refactor-circus/foundation](https://github.com/jayjfletcher/Foundation), the shared runtime of the Refactor Circus suite, and its local copies are gone. `ImpexServiceProvider` extends `PackageServiceProvider` and registers Impex with the suite's `PackageRegistry` (key `impex`); policies, the MCP server, Cortex and the Atrium plugin are wired through its helpers, reading the same `impex.*` config keys as before. Old → new:
  - `RefactorCircus\Impex\Contracts\ActionStartingEvent`, `ActionFinishedEvent`, `ModelLifecycleEvent` → `RefactorCircus\Foundation\Contracts\...` (listen to these to hear every package of the suite)
  - `RefactorCircus\Impex\Support\Models\Concerns\DispatchesModelEvents` → `RefactorCircus\Foundation\Models\Concerns\DispatchesModelEvents`
  - `RefactorCircus\Impex\Http\Request` → `RefactorCircus\Foundation\Http\Requests\Request`
  - `RefactorCircus\Impex\Mcp\Request` → `RefactorCircus\Foundation\Mcp\Requests\Request` (calls now run inside the `mcp` surface)
  - `RefactorCircus\Impex\Mcp\Tool` → `RefactorCircus\Foundation\Mcp\Tool`
  - `RefactorCircus\Impex\Support\Authorizer` → `RefactorCircus\Foundation\Auth\Authorizer`, built with `Authorizer::for(app(PackageRegistry::class)->get('impex'))` rather than resolved from the container
  - `RefactorCircus\Impex\Cortex\CortexIntegration` → `RefactorCircus\Foundation\Cortex\CortexIntegration`, built with `CortexIntegration::for($package)`; agent tool calls are now marked with the `cortex` surface
  - `RefactorCircus\Impex\Support\ServiceProvider` → `RefactorCircus\Foundation\Support\ServiceProvider` (the inbound channel route group moves into `MessageServiceProvider`)
  - `ImpexServer` extends `RefactorCircus\Foundation\Mcp\Server`, which serves the Cortex instructions override; `ImpexException` extends `RefactorCircus\Foundation\Exceptions\PackageException`; `Support\Policies\Policy` extends `RefactorCircus\Foundation\Policies\Policy`.
- Every `ImpexException` thrown during an HTTP request now answers `409` with its message as JSON, as `CannotSignalTerminalRunException` already did. A disabled flow, for example, was a `500`.

- The package is reorganised into domain modules (`src/Domains/Run`, `Flow`, `Signal`, `Batch`, `Message`, `Artifact`), mirroring the mono application's layout. Classes move namespaces and the models gain a `Model` suffix (`Run` → `RunModel`, ...); there are no aliases for the old class names, so update imports, `impex.policies` keys and any `impex.channels` profile or validator classes. The flow DSL base classes (`Flow`, `ResumableAction`) and the builders move to `Domains\Flow\Support`, and the engine and its collaborators to `Domains\Run\Services` - rebind them by their new names. Config keys, route names and paths, MCP tool names, publish tags, views, translations, tables and model event class names are unchanged. Package-wide pieces keep their names: `ImpexServiceProvider`, the `Impex` class and facade, `ImpexPlugin`, `Badges`, the event contracts, `Http\Request`, `Mcp\Request`, `Mcp\Tool`, `Mcp\ImpexServer`, `ImpexException`, `Support\Locks`, `Testing\Flows`, `CortexIntegration` and `PruneCommand`. The queued jobs (`DriveRun`, `ExecuteStep`, `ProcessBatchItem`, `SeedBatch`) keep their `RefactorCircus\Impex\Jobs` names, so jobs already on a queue still run. Each model keeps its old class name as its morph alias, so values stored under it (a run owner's `owner_type`, an audit subject) still resolve, and `ImpexSupportFeature` keeps its Pennant stored name (`RefactorCircus\Impex\Features\ImpexSupportFeature`). The JSON API routes now load from each domain (`routes/impex.php` is gone). References to Atrium, Cortex and PennantPlus follow their domain-module renames. Old → new:
  - `RefactorCircus\Impex\Access\Authorizer` → `RefactorCircus\Impex\Support\Authorizer`
  - `RefactorCircus\Impex\Actions\AttachRunOwnerAction` → `RefactorCircus\Impex\Domains\Run\Actions\AttachRunOwnerAction`
  - `RefactorCircus\Impex\Actions\CancelRunAction` → `RefactorCircus\Impex\Domains\Run\Actions\CancelRunAction`
  - `RefactorCircus\Impex\Actions\DetachRunOwnerAction` → `RefactorCircus\Impex\Domains\Run\Actions\DetachRunOwnerAction`
  - `RefactorCircus\Impex\Actions\ListChannelsAction` → `RefactorCircus\Impex\Domains\Message\Actions\ListChannelsAction`
  - `RefactorCircus\Impex\Actions\ListFlowsAction` → `RefactorCircus\Impex\Domains\Flow\Actions\ListFlowsAction`
  - `RefactorCircus\Impex\Actions\ListMessagesAction` → `RefactorCircus\Impex\Domains\Message\Actions\ListMessagesAction`
  - `RefactorCircus\Impex\Actions\ListRunOwnersAction` → `RefactorCircus\Impex\Domains\Run\Actions\ListRunOwnersAction`
  - `RefactorCircus\Impex\Actions\ListRunStepsAction` → `RefactorCircus\Impex\Domains\Run\Actions\ListRunStepsAction`
  - `RefactorCircus\Impex\Actions\ListRunsAction` → `RefactorCircus\Impex\Domains\Run\Actions\ListRunsAction`
  - `RefactorCircus\Impex\Actions\RetryRunAction` → `RefactorCircus\Impex\Domains\Run\Actions\RetryRunAction`
  - `RefactorCircus\Impex\Actions\RunFlowAction` → `RefactorCircus\Impex\Domains\Flow\Actions\RunFlowAction`
  - `RefactorCircus\Impex\Actions\ShowMessageAction` → `RefactorCircus\Impex\Domains\Message\Actions\ShowMessageAction`
  - `RefactorCircus\Impex\Actions\ShowRunAction` → `RefactorCircus\Impex\Domains\Run\Actions\ShowRunAction`
  - `RefactorCircus\Impex\Actions\SignalRunAction` → `RefactorCircus\Impex\Domains\Signal\Actions\SignalRunAction`
  - `RefactorCircus\Impex\Channels\ChannelConfig` → `RefactorCircus\Impex\Domains\Message\Data\ChannelConfig`
  - `RefactorCircus\Impex\Channels\ChannelRegistry` → `RefactorCircus\Impex\Domains\Message\Services\ChannelRegistry`
  - `RefactorCircus\Impex\Channels\Profiles\ProcessEverything` → `RefactorCircus\Impex\Domains\Message\Support\ProcessEverything`
  - `RefactorCircus\Impex\Channels\Validators\HmacSha256Validator` → `RefactorCircus\Impex\Domains\Message\Support\HmacSha256Validator`
  - `RefactorCircus\Impex\Console\Commands\RunFlowCommand` → `RefactorCircus\Impex\Domains\Flow\Console\Commands\RunFlowCommand`
  - `RefactorCircus\Impex\Console\Commands\SignalCommand` → `RefactorCircus\Impex\Domains\Signal\Console\Commands\SignalCommand`
  - `RefactorCircus\Impex\Console\Commands\TickCommand` → `RefactorCircus\Impex\Domains\Run\Console\Commands\TickCommand`
  - `RefactorCircus\Impex\Console\FlowArguments` → `RefactorCircus\Impex\Domains\Flow\Support\FlowArguments`
  - `RefactorCircus\Impex\Contracts\BatchSource` → `RefactorCircus\Impex\Domains\Batch\Contracts\BatchSource`
  - `RefactorCircus\Impex\Contracts\ChannelProfile` → `RefactorCircus\Impex\Domains\Message\Contracts\ChannelProfile`
  - `RefactorCircus\Impex\Contracts\Resumable` → `RefactorCircus\Impex\Domains\Flow\Contracts\Resumable`
  - `RefactorCircus\Impex\Contracts\RollbackStrategy` → `RefactorCircus\Impex\Domains\Run\Contracts\RollbackStrategy`
  - `RefactorCircus\Impex\Contracts\SignatureValidator` → `RefactorCircus\Impex\Domains\Message\Contracts\SignatureValidator`
  - `RefactorCircus\Impex\Enums\ArtifactKind` → `RefactorCircus\Impex\Domains\Artifact\Enums\ArtifactKind`
  - `RefactorCircus\Impex\Enums\ChildClosePolicy` → `RefactorCircus\Impex\Domains\Run\Enums\ChildClosePolicy`
  - `RefactorCircus\Impex\Enums\Direction` → `RefactorCircus\Impex\Domains\Message\Enums\Direction`
  - `RefactorCircus\Impex\Enums\ParallelFailure` → `RefactorCircus\Impex\Domains\Run\Enums\ParallelFailure`
  - `RefactorCircus\Impex\Enums\RollbackFailure` → `RefactorCircus\Impex\Domains\Run\Enums\RollbackFailure`
  - `RefactorCircus\Impex\Enums\RunStatus` → `RefactorCircus\Impex\Domains\Run\Enums\RunStatus`
  - `RefactorCircus\Impex\Enums\RunTrigger` → `RefactorCircus\Impex\Domains\Run\Enums\RunTrigger`
  - `RefactorCircus\Impex\Enums\StepPhase` → `RefactorCircus\Impex\Domains\Run\Enums\StepPhase`
  - `RefactorCircus\Impex\Enums\StepStatus` → `RefactorCircus\Impex\Domains\Run\Enums\StepStatus`
  - `RefactorCircus\Impex\Enums\StepType` → `RefactorCircus\Impex\Domains\Run\Enums\StepType`
  - `RefactorCircus\Impex\Enums\TimerKind` → `RefactorCircus\Impex\Domains\Signal\Enums\TimerKind`
  - `RefactorCircus\Impex\Events\Action\ChannelsListedActionEvent` → `RefactorCircus\Impex\Domains\Message\Events\ChannelsListedActionEvent`
  - `RefactorCircus\Impex\Events\Action\ChannelsListingActionEvent` → `RefactorCircus\Impex\Domains\Message\Events\ChannelsListingActionEvent`
  - `RefactorCircus\Impex\Events\Action\FlowRanActionEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowRanActionEvent`
  - `RefactorCircus\Impex\Events\Action\FlowRunningActionEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowRunningActionEvent`
  - `RefactorCircus\Impex\Events\Action\FlowsListedActionEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowsListedActionEvent`
  - `RefactorCircus\Impex\Events\Action\FlowsListingActionEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowsListingActionEvent`
  - `RefactorCircus\Impex\Events\Action\MessageShowingActionEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageShowingActionEvent`
  - `RefactorCircus\Impex\Events\Action\MessageShownActionEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageShownActionEvent`
  - `RefactorCircus\Impex\Events\Action\MessagesListedActionEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessagesListedActionEvent`
  - `RefactorCircus\Impex\Events\Action\MessagesListingActionEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessagesListingActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunCancelledActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunCancelledActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunCancellingActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunCancellingActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunOwnerAttachedActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerAttachedActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunOwnerAttachingActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerAttachingActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunOwnerDetachedActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerDetachedActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunOwnerDetachingActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerDetachingActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunOwnersListedActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnersListedActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunOwnersListingActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnersListingActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunRetriedActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunRetriedActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunRetryingActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunRetryingActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunShowingActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunShowingActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunShownActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunShownActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunSignalledActionEvent` → `RefactorCircus\Impex\Domains\Signal\Events\RunSignalledActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunSignallingActionEvent` → `RefactorCircus\Impex\Domains\Signal\Events\RunSignallingActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunStepsListedActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepsListedActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunStepsListingActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepsListingActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunsListedActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunsListedActionEvent`
  - `RefactorCircus\Impex\Events\Action\RunsListingActionEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunsListingActionEvent`
  - `RefactorCircus\Impex\Events\MessageRecorded` → `RefactorCircus\Impex\Domains\Message\Events\MessageRecorded`
  - `RefactorCircus\Impex\Events\Model\ArtifactCreatedEvent` → `RefactorCircus\Impex\Domains\Artifact\Events\ArtifactCreatedEvent`
  - `RefactorCircus\Impex\Events\Model\ArtifactCreatingEvent` → `RefactorCircus\Impex\Domains\Artifact\Events\ArtifactCreatingEvent`
  - `RefactorCircus\Impex\Events\Model\ArtifactDeletedEvent` → `RefactorCircus\Impex\Domains\Artifact\Events\ArtifactDeletedEvent`
  - `RefactorCircus\Impex\Events\Model\ArtifactDeletingEvent` → `RefactorCircus\Impex\Domains\Artifact\Events\ArtifactDeletingEvent`
  - `RefactorCircus\Impex\Events\Model\ArtifactReplicatingEvent` → `RefactorCircus\Impex\Domains\Artifact\Events\ArtifactReplicatingEvent`
  - `RefactorCircus\Impex\Events\Model\ArtifactRetrievedEvent` → `RefactorCircus\Impex\Domains\Artifact\Events\ArtifactRetrievedEvent`
  - `RefactorCircus\Impex\Events\Model\ArtifactSavedEvent` → `RefactorCircus\Impex\Domains\Artifact\Events\ArtifactSavedEvent`
  - `RefactorCircus\Impex\Events\Model\ArtifactSavingEvent` → `RefactorCircus\Impex\Domains\Artifact\Events\ArtifactSavingEvent`
  - `RefactorCircus\Impex\Events\Model\ArtifactUpdatedEvent` → `RefactorCircus\Impex\Domains\Artifact\Events\ArtifactUpdatedEvent`
  - `RefactorCircus\Impex\Events\Model\ArtifactUpdatingEvent` → `RefactorCircus\Impex\Domains\Artifact\Events\ArtifactUpdatingEvent`
  - `RefactorCircus\Impex\Events\Model\BatchCreatedEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchCreatedEvent`
  - `RefactorCircus\Impex\Events\Model\BatchCreatingEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchCreatingEvent`
  - `RefactorCircus\Impex\Events\Model\BatchDeletedEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchDeletedEvent`
  - `RefactorCircus\Impex\Events\Model\BatchDeletingEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchDeletingEvent`
  - `RefactorCircus\Impex\Events\Model\BatchItemCreatedEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchItemCreatedEvent`
  - `RefactorCircus\Impex\Events\Model\BatchItemCreatingEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchItemCreatingEvent`
  - `RefactorCircus\Impex\Events\Model\BatchItemDeletedEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchItemDeletedEvent`
  - `RefactorCircus\Impex\Events\Model\BatchItemDeletingEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchItemDeletingEvent`
  - `RefactorCircus\Impex\Events\Model\BatchItemReplicatingEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchItemReplicatingEvent`
  - `RefactorCircus\Impex\Events\Model\BatchItemRetrievedEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchItemRetrievedEvent`
  - `RefactorCircus\Impex\Events\Model\BatchItemSavedEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchItemSavedEvent`
  - `RefactorCircus\Impex\Events\Model\BatchItemSavingEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchItemSavingEvent`
  - `RefactorCircus\Impex\Events\Model\BatchItemUpdatedEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchItemUpdatedEvent`
  - `RefactorCircus\Impex\Events\Model\BatchItemUpdatingEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchItemUpdatingEvent`
  - `RefactorCircus\Impex\Events\Model\BatchReplicatingEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchReplicatingEvent`
  - `RefactorCircus\Impex\Events\Model\BatchRetrievedEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchRetrievedEvent`
  - `RefactorCircus\Impex\Events\Model\BatchSavedEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchSavedEvent`
  - `RefactorCircus\Impex\Events\Model\BatchSavingEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchSavingEvent`
  - `RefactorCircus\Impex\Events\Model\BatchUpdatedEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchUpdatedEvent`
  - `RefactorCircus\Impex\Events\Model\BatchUpdatingEvent` → `RefactorCircus\Impex\Domains\Batch\Events\BatchUpdatingEvent`
  - `RefactorCircus\Impex\Events\Model\FlowOverrideCreatedEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowOverrideCreatedEvent`
  - `RefactorCircus\Impex\Events\Model\FlowOverrideCreatingEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowOverrideCreatingEvent`
  - `RefactorCircus\Impex\Events\Model\FlowOverrideDeletedEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowOverrideDeletedEvent`
  - `RefactorCircus\Impex\Events\Model\FlowOverrideDeletingEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowOverrideDeletingEvent`
  - `RefactorCircus\Impex\Events\Model\FlowOverrideReplicatingEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowOverrideReplicatingEvent`
  - `RefactorCircus\Impex\Events\Model\FlowOverrideRetrievedEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowOverrideRetrievedEvent`
  - `RefactorCircus\Impex\Events\Model\FlowOverrideSavedEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowOverrideSavedEvent`
  - `RefactorCircus\Impex\Events\Model\FlowOverrideSavingEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowOverrideSavingEvent`
  - `RefactorCircus\Impex\Events\Model\FlowOverrideUpdatedEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowOverrideUpdatedEvent`
  - `RefactorCircus\Impex\Events\Model\FlowOverrideUpdatingEvent` → `RefactorCircus\Impex\Domains\Flow\Events\FlowOverrideUpdatingEvent`
  - `RefactorCircus\Impex\Events\Model\MessageCreatedEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageCreatedEvent`
  - `RefactorCircus\Impex\Events\Model\MessageCreatingEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageCreatingEvent`
  - `RefactorCircus\Impex\Events\Model\MessageDeletedEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageDeletedEvent`
  - `RefactorCircus\Impex\Events\Model\MessageDeletingEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageDeletingEvent`
  - `RefactorCircus\Impex\Events\Model\MessageReplicatingEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageReplicatingEvent`
  - `RefactorCircus\Impex\Events\Model\MessageRetrievedEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageRetrievedEvent`
  - `RefactorCircus\Impex\Events\Model\MessageSavedEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageSavedEvent`
  - `RefactorCircus\Impex\Events\Model\MessageSavingEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageSavingEvent`
  - `RefactorCircus\Impex\Events\Model\MessageUpdatedEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageUpdatedEvent`
  - `RefactorCircus\Impex\Events\Model\MessageUpdatingEvent` → `RefactorCircus\Impex\Domains\Message\Events\MessageUpdatingEvent`
  - `RefactorCircus\Impex\Events\Model\RunCreatedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunCreatedEvent`
  - `RefactorCircus\Impex\Events\Model\RunCreatingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunCreatingEvent`
  - `RefactorCircus\Impex\Events\Model\RunDeletedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunDeletedEvent`
  - `RefactorCircus\Impex\Events\Model\RunDeletingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunDeletingEvent`
  - `RefactorCircus\Impex\Events\Model\RunOwnerCreatedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerCreatedEvent`
  - `RefactorCircus\Impex\Events\Model\RunOwnerCreatingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerCreatingEvent`
  - `RefactorCircus\Impex\Events\Model\RunOwnerDeletedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerDeletedEvent`
  - `RefactorCircus\Impex\Events\Model\RunOwnerDeletingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerDeletingEvent`
  - `RefactorCircus\Impex\Events\Model\RunOwnerReplicatingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerReplicatingEvent`
  - `RefactorCircus\Impex\Events\Model\RunOwnerRetrievedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerRetrievedEvent`
  - `RefactorCircus\Impex\Events\Model\RunOwnerSavedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerSavedEvent`
  - `RefactorCircus\Impex\Events\Model\RunOwnerSavingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerSavingEvent`
  - `RefactorCircus\Impex\Events\Model\RunOwnerUpdatedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerUpdatedEvent`
  - `RefactorCircus\Impex\Events\Model\RunOwnerUpdatingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunOwnerUpdatingEvent`
  - `RefactorCircus\Impex\Events\Model\RunReplicatingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunReplicatingEvent`
  - `RefactorCircus\Impex\Events\Model\RunRetrievedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunRetrievedEvent`
  - `RefactorCircus\Impex\Events\Model\RunSavedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunSavedEvent`
  - `RefactorCircus\Impex\Events\Model\RunSavingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunSavingEvent`
  - `RefactorCircus\Impex\Events\Model\RunStepCreatedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepCreatedEvent`
  - `RefactorCircus\Impex\Events\Model\RunStepCreatingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepCreatingEvent`
  - `RefactorCircus\Impex\Events\Model\RunStepDeletedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepDeletedEvent`
  - `RefactorCircus\Impex\Events\Model\RunStepDeletingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepDeletingEvent`
  - `RefactorCircus\Impex\Events\Model\RunStepReplicatingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepReplicatingEvent`
  - `RefactorCircus\Impex\Events\Model\RunStepRetrievedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepRetrievedEvent`
  - `RefactorCircus\Impex\Events\Model\RunStepSavedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepSavedEvent`
  - `RefactorCircus\Impex\Events\Model\RunStepSavingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepSavingEvent`
  - `RefactorCircus\Impex\Events\Model\RunStepUpdatedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepUpdatedEvent`
  - `RefactorCircus\Impex\Events\Model\RunStepUpdatingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunStepUpdatingEvent`
  - `RefactorCircus\Impex\Events\Model\RunUpdatedEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunUpdatedEvent`
  - `RefactorCircus\Impex\Events\Model\RunUpdatingEvent` → `RefactorCircus\Impex\Domains\Run\Events\RunUpdatingEvent`
  - `RefactorCircus\Impex\Events\Model\SignalCreatedEvent` → `RefactorCircus\Impex\Domains\Signal\Events\SignalCreatedEvent`
  - `RefactorCircus\Impex\Events\Model\SignalCreatingEvent` → `RefactorCircus\Impex\Domains\Signal\Events\SignalCreatingEvent`
  - `RefactorCircus\Impex\Events\Model\SignalDeletedEvent` → `RefactorCircus\Impex\Domains\Signal\Events\SignalDeletedEvent`
  - `RefactorCircus\Impex\Events\Model\SignalDeletingEvent` → `RefactorCircus\Impex\Domains\Signal\Events\SignalDeletingEvent`
  - `RefactorCircus\Impex\Events\Model\SignalReplicatingEvent` → `RefactorCircus\Impex\Domains\Signal\Events\SignalReplicatingEvent`
  - `RefactorCircus\Impex\Events\Model\SignalRetrievedEvent` → `RefactorCircus\Impex\Domains\Signal\Events\SignalRetrievedEvent`
  - `RefactorCircus\Impex\Events\Model\SignalSavedEvent` → `RefactorCircus\Impex\Domains\Signal\Events\SignalSavedEvent`
  - `RefactorCircus\Impex\Events\Model\SignalSavingEvent` → `RefactorCircus\Impex\Domains\Signal\Events\SignalSavingEvent`
  - `RefactorCircus\Impex\Events\Model\SignalUpdatedEvent` → `RefactorCircus\Impex\Domains\Signal\Events\SignalUpdatedEvent`
  - `RefactorCircus\Impex\Events\Model\SignalUpdatingEvent` → `RefactorCircus\Impex\Domains\Signal\Events\SignalUpdatingEvent`
  - `RefactorCircus\Impex\Events\Model\TimerCreatedEvent` → `RefactorCircus\Impex\Domains\Signal\Events\TimerCreatedEvent`
  - `RefactorCircus\Impex\Events\Model\TimerCreatingEvent` → `RefactorCircus\Impex\Domains\Signal\Events\TimerCreatingEvent`
  - `RefactorCircus\Impex\Events\Model\TimerDeletedEvent` → `RefactorCircus\Impex\Domains\Signal\Events\TimerDeletedEvent`
  - `RefactorCircus\Impex\Events\Model\TimerDeletingEvent` → `RefactorCircus\Impex\Domains\Signal\Events\TimerDeletingEvent`
  - `RefactorCircus\Impex\Events\Model\TimerReplicatingEvent` → `RefactorCircus\Impex\Domains\Signal\Events\TimerReplicatingEvent`
  - `RefactorCircus\Impex\Events\Model\TimerRetrievedEvent` → `RefactorCircus\Impex\Domains\Signal\Events\TimerRetrievedEvent`
  - `RefactorCircus\Impex\Events\Model\TimerSavedEvent` → `RefactorCircus\Impex\Domains\Signal\Events\TimerSavedEvent`
  - `RefactorCircus\Impex\Events\Model\TimerSavingEvent` → `RefactorCircus\Impex\Domains\Signal\Events\TimerSavingEvent`
  - `RefactorCircus\Impex\Events\Model\TimerUpdatedEvent` → `RefactorCircus\Impex\Domains\Signal\Events\TimerUpdatedEvent`
  - `RefactorCircus\Impex\Events\Model\TimerUpdatingEvent` → `RefactorCircus\Impex\Domains\Signal\Events\TimerUpdatingEvent`
  - `RefactorCircus\Impex\Events\RunCompleted` → `RefactorCircus\Impex\Domains\Run\Events\RunCompleted`
  - `RefactorCircus\Impex\Events\RunFailed` → `RefactorCircus\Impex\Domains\Run\Events\RunFailed`
  - `RefactorCircus\Impex\Events\RunStarted` → `RefactorCircus\Impex\Domains\Run\Events\RunStarted`
  - `RefactorCircus\Impex\Events\StepCompleted` → `RefactorCircus\Impex\Domains\Run\Events\StepCompleted`
  - `RefactorCircus\Impex\Events\StepFailed` → `RefactorCircus\Impex\Domains\Run\Events\StepFailed`
  - `RefactorCircus\Impex\Exceptions\BatchFailedException` → `RefactorCircus\Impex\Domains\Batch\Exceptions\BatchFailedException`
  - `RefactorCircus\Impex\Exceptions\CannotSignalTerminalRunException` → `RefactorCircus\Impex\Domains\Signal\Exceptions\CannotSignalTerminalRunException`
  - `RefactorCircus\Impex\Exceptions\DeadlineExceededException` → `RefactorCircus\Impex\Domains\Run\Exceptions\DeadlineExceededException`
  - `RefactorCircus\Impex\Exceptions\DisabledFlowException` → `RefactorCircus\Impex\Domains\Flow\Exceptions\DisabledFlowException`
  - `RefactorCircus\Impex\Exceptions\FanOutTooLargeException` → `RefactorCircus\Impex\Domains\Flow\Exceptions\FanOutTooLargeException`
  - `RefactorCircus\Impex\Exceptions\FlowCollisionException` → `RefactorCircus\Impex\Domains\Flow\Exceptions\FlowCollisionException`
  - `RefactorCircus\Impex\Exceptions\FlowVersionMismatchException` → `RefactorCircus\Impex\Domains\Flow\Exceptions\FlowVersionMismatchException`
  - `RefactorCircus\Impex\Exceptions\HistoryMismatchException` → `RefactorCircus\Impex\Domains\Run\Exceptions\HistoryMismatchException`
  - `RefactorCircus\Impex\Exceptions\PayloadException` → `RefactorCircus\Impex\Domains\Artifact\Exceptions\PayloadException`
  - `RefactorCircus\Impex\Exceptions\SignalTimeoutException` → `RefactorCircus\Impex\Domains\Signal\Exceptions\SignalTimeoutException`
  - `RefactorCircus\Impex\Exceptions\StalledStepException` → `RefactorCircus\Impex\Domains\Run\Exceptions\StalledStepException`
  - `RefactorCircus\Impex\Exceptions\StepFailedException` → `RefactorCircus\Impex\Domains\Run\Exceptions\StepFailedException`
  - `RefactorCircus\Impex\Exceptions\UnknownChannelException` → `RefactorCircus\Impex\Domains\Message\Exceptions\UnknownChannelException`
  - `RefactorCircus\Impex\Exceptions\UnknownFlowException` → `RefactorCircus\Impex\Domains\Flow\Exceptions\UnknownFlowException`
  - `RefactorCircus\Impex\Features\ImpexSupportFeature` → `RefactorCircus\Impex\Atrium\Features\ImpexSupportFeature`
  - `RefactorCircus\Impex\Flows\Builders\ActionBuilder` → `RefactorCircus\Impex\Domains\Flow\Support\ActionBuilder`
  - `RefactorCircus\Impex\Flows\Builders\BatchBuilder` → `RefactorCircus\Impex\Domains\Flow\Support\BatchBuilder`
  - `RefactorCircus\Impex\Flows\Builders\ChildBuilder` → `RefactorCircus\Impex\Domains\Flow\Support\ChildBuilder`
  - `RefactorCircus\Impex\Flows\Builders\FanOutBuilder` → `RefactorCircus\Impex\Domains\Flow\Support\FanOutBuilder`
  - `RefactorCircus\Impex\Flows\Builders\ParallelBuilder` → `RefactorCircus\Impex\Domains\Flow\Support\ParallelBuilder`
  - `RefactorCircus\Impex\Flows\Builders\SignalBuilder` → `RefactorCircus\Impex\Domains\Flow\Support\SignalBuilder`
  - `RefactorCircus\Impex\Flows\Builders\UnitBuilder` → `RefactorCircus\Impex\Domains\Flow\Support\UnitBuilder`
  - `RefactorCircus\Impex\Flows\Concerns\CanResume` → `RefactorCircus\Impex\Domains\Flow\Concerns\CanResume`
  - `RefactorCircus\Impex\Flows\Flow` → `RefactorCircus\Impex\Domains\Flow\Support\Flow`
  - `RefactorCircus\Impex\Flows\FlowRegistry` → `RefactorCircus\Impex\Domains\Flow\Services\FlowRegistry`
  - `RefactorCircus\Impex\Flows\ResumableAction` → `RefactorCircus\Impex\Domains\Flow\Support\ResumableAction`
  - `RefactorCircus\Impex\Http\Controllers\ChannelController` → `RefactorCircus\Impex\Domains\Message\Http\Controllers\ChannelController`
  - `RefactorCircus\Impex\Http\Controllers\ChannelIndexController` → `RefactorCircus\Impex\Domains\Message\Http\Controllers\ChannelIndexController`
  - `RefactorCircus\Impex\Http\Controllers\FlowController` → `RefactorCircus\Impex\Domains\Flow\Http\Controllers\FlowController`
  - `RefactorCircus\Impex\Http\Controllers\MessageController` → `RefactorCircus\Impex\Domains\Message\Http\Controllers\MessageController`
  - `RefactorCircus\Impex\Http\Controllers\RunController` → `RefactorCircus\Impex\Domains\Run\Http\Controllers\RunController`
  - `RefactorCircus\Impex\Http\Controllers\RunOwnerController` → `RefactorCircus\Impex\Domains\Run\Http\Controllers\RunOwnerController`
  - `RefactorCircus\Impex\Http\Controllers\RunSignalController` → `RefactorCircus\Impex\Domains\Signal\Http\Controllers\RunSignalController`
  - `RefactorCircus\Impex\Http\Controllers\RunStepController` → `RefactorCircus\Impex\Domains\Run\Http\Controllers\RunStepController`
  - `RefactorCircus\Impex\Http\Requests\CancelRunRequest` → `RefactorCircus\Impex\Domains\Run\Http\Requests\CancelRunRequest`
  - `RefactorCircus\Impex\Http\Requests\DeleteRunOwnerRequest` → `RefactorCircus\Impex\Domains\Run\Http\Requests\DeleteRunOwnerRequest`
  - `RefactorCircus\Impex\Http\Requests\IndexChannelsRequest` → `RefactorCircus\Impex\Domains\Message\Http\Requests\IndexChannelsRequest`
  - `RefactorCircus\Impex\Http\Requests\IndexFlowsRequest` → `RefactorCircus\Impex\Domains\Flow\Http\Requests\IndexFlowsRequest`
  - `RefactorCircus\Impex\Http\Requests\IndexMessagesRequest` → `RefactorCircus\Impex\Domains\Message\Http\Requests\IndexMessagesRequest`
  - `RefactorCircus\Impex\Http\Requests\IndexRunOwnersRequest` → `RefactorCircus\Impex\Domains\Run\Http\Requests\IndexRunOwnersRequest`
  - `RefactorCircus\Impex\Http\Requests\IndexRunStepsRequest` → `RefactorCircus\Impex\Domains\Run\Http\Requests\IndexRunStepsRequest`
  - `RefactorCircus\Impex\Http\Requests\IndexRunsRequest` → `RefactorCircus\Impex\Domains\Run\Http\Requests\IndexRunsRequest`
  - `RefactorCircus\Impex\Http\Requests\RetryRunRequest` → `RefactorCircus\Impex\Domains\Run\Http\Requests\RetryRunRequest`
  - `RefactorCircus\Impex\Http\Requests\RunRequest` → `RefactorCircus\Impex\Domains\Run\Http\Requests\RunRequest`
  - `RefactorCircus\Impex\Http\Requests\ShowMessageRequest` → `RefactorCircus\Impex\Domains\Message\Http\Requests\ShowMessageRequest`
  - `RefactorCircus\Impex\Http\Requests\ShowRunRequest` → `RefactorCircus\Impex\Domains\Run\Http\Requests\ShowRunRequest`
  - `RefactorCircus\Impex\Http\Requests\StoreFlowRunRequest` → `RefactorCircus\Impex\Domains\Flow\Http\Requests\StoreFlowRunRequest`
  - `RefactorCircus\Impex\Http\Requests\StoreRunOwnerRequest` → `RefactorCircus\Impex\Domains\Run\Http\Requests\StoreRunOwnerRequest`
  - `RefactorCircus\Impex\Http\Requests\StoreRunSignalRequest` → `RefactorCircus\Impex\Domains\Signal\Http\Requests\StoreRunSignalRequest`
  - `RefactorCircus\Impex\Http\Resources\MessageResource` → `RefactorCircus\Impex\Domains\Message\Resources\MessageResource`
  - `RefactorCircus\Impex\Http\Resources\RunOwnerResource` → `RefactorCircus\Impex\Domains\Run\Resources\RunOwnerResource`
  - `RefactorCircus\Impex\Http\Resources\RunResource` → `RefactorCircus\Impex\Domains\Run\Resources\RunResource`
  - `RefactorCircus\Impex\Http\Resources\RunStepResource` → `RefactorCircus\Impex\Domains\Run\Resources\RunStepResource`
  - `RefactorCircus\Impex\Http\Resources\SignalResource` → `RefactorCircus\Impex\Domains\Signal\Resources\SignalResource`
  - `RefactorCircus\Impex\Http\Ui\ChannelUiController` → `RefactorCircus\Impex\Atrium\Http\Controllers\ChannelUiController`
  - `RefactorCircus\Impex\Http\Ui\Concerns\AuthorizesScreens` → `RefactorCircus\Impex\Atrium\Http\Controllers\Concerns\AuthorizesScreens`
  - `RefactorCircus\Impex\Http\Ui\FlowUiController` → `RefactorCircus\Impex\Atrium\Http\Controllers\FlowUiController`
  - `RefactorCircus\Impex\Http\Ui\MessageUiController` → `RefactorCircus\Impex\Atrium\Http\Controllers\MessageUiController`
  - `RefactorCircus\Impex\Http\Ui\RunUiController` → `RefactorCircus\Impex\Atrium\Http\Controllers\RunUiController`
  - `RefactorCircus\Impex\Http\Ui\ScreenAccess` → `RefactorCircus\Impex\Atrium\ScreenAccess`
  - `RefactorCircus\Impex\Mcp\Requests\AttachRunOwnerMcpRequest` → `RefactorCircus\Impex\Domains\Run\Mcp\Requests\AttachRunOwnerMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\CancelRunMcpRequest` → `RefactorCircus\Impex\Domains\Run\Mcp\Requests\CancelRunMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\DetachRunOwnerMcpRequest` → `RefactorCircus\Impex\Domains\Run\Mcp\Requests\DetachRunOwnerMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\ListChannelsMcpRequest` → `RefactorCircus\Impex\Domains\Message\Mcp\Requests\ListChannelsMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\ListFlowsMcpRequest` → `RefactorCircus\Impex\Domains\Flow\Mcp\Requests\ListFlowsMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\ListMessagesMcpRequest` → `RefactorCircus\Impex\Domains\Message\Mcp\Requests\ListMessagesMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\ListRunOwnersMcpRequest` → `RefactorCircus\Impex\Domains\Run\Mcp\Requests\ListRunOwnersMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\ListRunStepsMcpRequest` → `RefactorCircus\Impex\Domains\Run\Mcp\Requests\ListRunStepsMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\ListRunsMcpRequest` → `RefactorCircus\Impex\Domains\Run\Mcp\Requests\ListRunsMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\RetryRunMcpRequest` → `RefactorCircus\Impex\Domains\Run\Mcp\Requests\RetryRunMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\RunFlowMcpRequest` → `RefactorCircus\Impex\Domains\Flow\Mcp\Requests\RunFlowMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\RunRequest` → `RefactorCircus\Impex\Domains\Run\Mcp\Requests\RunRequest`
  - `RefactorCircus\Impex\Mcp\Requests\ShowMessageMcpRequest` → `RefactorCircus\Impex\Domains\Message\Mcp\Requests\ShowMessageMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\ShowRunMcpRequest` → `RefactorCircus\Impex\Domains\Run\Mcp\Requests\ShowRunMcpRequest`
  - `RefactorCircus\Impex\Mcp\Requests\SignalRunMcpRequest` → `RefactorCircus\Impex\Domains\Signal\Mcp\Requests\SignalRunMcpRequest`
  - `RefactorCircus\Impex\Mcp\Tools\AttachRunOwnerTool` → `RefactorCircus\Impex\Domains\Run\Mcp\Tools\AttachRunOwnerTool`
  - `RefactorCircus\Impex\Mcp\Tools\CancelRunTool` → `RefactorCircus\Impex\Domains\Run\Mcp\Tools\CancelRunTool`
  - `RefactorCircus\Impex\Mcp\Tools\DetachRunOwnerTool` → `RefactorCircus\Impex\Domains\Run\Mcp\Tools\DetachRunOwnerTool`
  - `RefactorCircus\Impex\Mcp\Tools\ListChannelsTool` → `RefactorCircus\Impex\Domains\Message\Mcp\Tools\ListChannelsTool`
  - `RefactorCircus\Impex\Mcp\Tools\ListFlowsTool` → `RefactorCircus\Impex\Domains\Flow\Mcp\Tools\ListFlowsTool`
  - `RefactorCircus\Impex\Mcp\Tools\ListMessagesTool` → `RefactorCircus\Impex\Domains\Message\Mcp\Tools\ListMessagesTool`
  - `RefactorCircus\Impex\Mcp\Tools\ListRunOwnersTool` → `RefactorCircus\Impex\Domains\Run\Mcp\Tools\ListRunOwnersTool`
  - `RefactorCircus\Impex\Mcp\Tools\ListRunStepsTool` → `RefactorCircus\Impex\Domains\Run\Mcp\Tools\ListRunStepsTool`
  - `RefactorCircus\Impex\Mcp\Tools\ListRunsTool` → `RefactorCircus\Impex\Domains\Run\Mcp\Tools\ListRunsTool`
  - `RefactorCircus\Impex\Mcp\Tools\RetryRunTool` → `RefactorCircus\Impex\Domains\Run\Mcp\Tools\RetryRunTool`
  - `RefactorCircus\Impex\Mcp\Tools\RunFlowTool` → `RefactorCircus\Impex\Domains\Flow\Mcp\Tools\RunFlowTool`
  - `RefactorCircus\Impex\Mcp\Tools\ShowMessageTool` → `RefactorCircus\Impex\Domains\Message\Mcp\Tools\ShowMessageTool`
  - `RefactorCircus\Impex\Mcp\Tools\ShowRunTool` → `RefactorCircus\Impex\Domains\Run\Mcp\Tools\ShowRunTool`
  - `RefactorCircus\Impex\Mcp\Tools\SignalRunTool` → `RefactorCircus\Impex\Domains\Signal\Mcp\Tools\SignalRunTool`
  - `RefactorCircus\Impex\Models\Artifact` → `RefactorCircus\Impex\Domains\Artifact\Models\ArtifactModel`
  - `RefactorCircus\Impex\Models\Batch` → `RefactorCircus\Impex\Domains\Batch\Models\BatchModel`
  - `RefactorCircus\Impex\Models\BatchItem` → `RefactorCircus\Impex\Domains\Batch\Models\BatchItemModel`
  - `RefactorCircus\Impex\Models\Concerns\DispatchesModelEvents` → `RefactorCircus\Impex\Support\Models\Concerns\DispatchesModelEvents`
  - `RefactorCircus\Impex\Models\FlowOverride` → `RefactorCircus\Impex\Domains\Flow\Models\FlowOverrideModel`
  - `RefactorCircus\Impex\Models\Message` → `RefactorCircus\Impex\Domains\Message\Models\MessageModel`
  - `RefactorCircus\Impex\Models\Run` → `RefactorCircus\Impex\Domains\Run\Models\RunModel`
  - `RefactorCircus\Impex\Models\RunOwner` → `RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel`
  - `RefactorCircus\Impex\Models\RunStep` → `RefactorCircus\Impex\Domains\Run\Models\RunStepModel`
  - `RefactorCircus\Impex\Models\Signal` → `RefactorCircus\Impex\Domains\Signal\Models\SignalModel`
  - `RefactorCircus\Impex\Models\Timer` → `RefactorCircus\Impex\Domains\Signal\Models\TimerModel`
  - `RefactorCircus\Impex\Policies\ArtifactPolicy` → `RefactorCircus\Impex\Domains\Artifact\Policies\ArtifactPolicy`
  - `RefactorCircus\Impex\Policies\BatchItemPolicy` → `RefactorCircus\Impex\Domains\Batch\Policies\BatchItemPolicy`
  - `RefactorCircus\Impex\Policies\BatchPolicy` → `RefactorCircus\Impex\Domains\Batch\Policies\BatchPolicy`
  - `RefactorCircus\Impex\Policies\FlowOverridePolicy` → `RefactorCircus\Impex\Domains\Flow\Policies\FlowOverridePolicy`
  - `RefactorCircus\Impex\Policies\MessagePolicy` → `RefactorCircus\Impex\Domains\Message\Policies\MessagePolicy`
  - `RefactorCircus\Impex\Policies\Policy` → `RefactorCircus\Impex\Support\Policies\Policy`
  - `RefactorCircus\Impex\Policies\RunOwnerPolicy` → `RefactorCircus\Impex\Domains\Run\Policies\RunOwnerPolicy`
  - `RefactorCircus\Impex\Policies\RunPolicy` → `RefactorCircus\Impex\Domains\Run\Policies\RunPolicy`
  - `RefactorCircus\Impex\Policies\RunStepPolicy` → `RefactorCircus\Impex\Domains\Run\Policies\RunStepPolicy`
  - `RefactorCircus\Impex\Policies\SignalPolicy` → `RefactorCircus\Impex\Domains\Signal\Policies\SignalPolicy`
  - `RefactorCircus\Impex\Policies\TimerPolicy` → `RefactorCircus\Impex\Domains\Signal\Policies\TimerPolicy`
  - `RefactorCircus\Impex\Runtime\BatchChunk` → `RefactorCircus\Impex\Domains\Batch\Data\BatchChunk`
  - `RefactorCircus\Impex\Runtime\BatchChunkItem` → `RefactorCircus\Impex\Domains\Batch\Data\BatchChunkItem`
  - `RefactorCircus\Impex\Runtime\BatchRunner` → `RefactorCircus\Impex\Domains\Batch\Services\BatchRunner`
  - `RefactorCircus\Impex\Runtime\Children` → `RefactorCircus\Impex\Domains\Run\Services\Children`
  - `RefactorCircus\Impex\Runtime\Context` → `RefactorCircus\Impex\Domains\Run\Support\Context`
  - `RefactorCircus\Impex\Runtime\Engine` → `RefactorCircus\Impex\Domains\Run\Services\Engine`
  - `RefactorCircus\Impex\Runtime\EngineOptions` → `RefactorCircus\Impex\Domains\Run\Services\EngineOptions`
  - `RefactorCircus\Impex\Runtime\Failure` → `RefactorCircus\Impex\Domains\Run\Support\Failure`
  - `RefactorCircus\Impex\Runtime\JobRouter` → `RefactorCircus\Impex\Domains\Run\Services\JobRouter`
  - `RefactorCircus\Impex\Runtime\Resume` → `RefactorCircus\Impex\Domains\Run\Data\Resume`
  - `RefactorCircus\Impex\Runtime\Rollbacks` → `RefactorCircus\Impex\Domains\Run\Services\Rollbacks`
  - `RefactorCircus\Impex\Runtime\RunHandle` → `RefactorCircus\Impex\Domains\Run\Support\RunHandle`
  - `RefactorCircus\Impex\Runtime\RunQuery` → `RefactorCircus\Impex\Domains\Run\Support\RunQuery`
  - `RefactorCircus\Impex\Runtime\StepDeadline` → `RefactorCircus\Impex\Domains\Run\Data\StepDeadline`
  - `RefactorCircus\Impex\Runtime\StepDescriptor` → `RefactorCircus\Impex\Domains\Run\Data\StepDescriptor`
  - `RefactorCircus\Impex\Runtime\StepWriter` → `RefactorCircus\Impex\Domains\Run\Services\StepWriter`
  - `RefactorCircus\Impex\Runtime\Suspended` → `RefactorCircus\Impex\Domains\Run\Support\Suspended`
  - `RefactorCircus\Impex\Runtime\SweepReport` → `RefactorCircus\Impex\Domains\Run\Data\SweepReport`
  - `RefactorCircus\Impex\Runtime\Sweeper` → `RefactorCircus\Impex\Domains\Run\Services\Sweeper`
  - `RefactorCircus\Impex\Runtime\Waits` → `RefactorCircus\Impex\Domains\Signal\Services\Waits`
  - `RefactorCircus\Impex\Support\MessageRecorder` → `RefactorCircus\Impex\Domains\Message\Services\MessageRecorder`
  - `RefactorCircus\Impex\Support\OutboundRecorder` → `RefactorCircus\Impex\Domains\Message\Services\OutboundRecorder`
  - `RefactorCircus\Impex\Support\PayloadStore` → `RefactorCircus\Impex\Domains\Artifact\Services\PayloadStore`

### Added

- With an audit log (refactor-circus/keen) installed, the run and message pages show that record's history and the runs page the whole of Impex's, through `<x-atrium::audit-trail source="impex" />`. Nothing renders without one; run steps stay out of it.
- The run, message and settings details use `x-atrium::description-list`.
- A test asserts `AtriumStyles::missingClasses()` and `inlineStyles()` are empty for Impex's views.
- `GET impex/history` (`impex.history.index`) and the `list-impex-history-tool` MCP tool list Impex's audit entries, newest first, once an audit log ([refactor-circus/keen](https://github.com/jayjfletcher/Keen)) is installed. Until then the route answers `404` and the tool explains that none is installed.

- Atrium screens follow Atrium's screen conventions (needs Atrium f5eb488 or later): every action is an icon button (`<x-atrium::icon-button>`), run, step, flow and channel/message signature states are status dots (the `impex::ui.partials.status-dot` partial, with `data-status`), and every Impex nav item has a Heroicons icon. `Badges::forStatus()`, `forFlow()` and `forSignature()` decide every colour; `info` is kept for pending (`pending`, and a run `waiting` on a signal).
- The dashboard asks the same policies as the JSON API and MCP tools: nav items, widgets, search, cards and buttons are shown only when their action would be allowed, through `RefactorCircus\Impex\Http\Ui\ScreenAccess` and the `@impexCan` Blade conditional.
- `ImpexSupportFeature` (needs `refactor-circus/pennantplus`) and `impex.atrium.features`: turning the feature off globally hides Impex in Atrium and 404s its pages. Classes that are not installed are skipped.

- `impex.atrium.show_all` (default `false`): dashboard operators. `true` makes everyone past Atrium's gate an operator, a string names a Gate ability that does. On the Atrium screens only, an operator's lists, nav badge, widgets and search cover every run and message (including unowned scheduled and channel runs) and every Impex control is allowed to them; the JSON API and MCP tools are unaffected. `ScreenAccess::viewer()`, `operator()` and `allowsFor()` expose the decision.

### Changed

- `ImpexPlugin::features()` uses Atrium's `featuresFromConfig('impex.atrium.features')`, and the plugin's `key()` and `label()` come from Atrium's base derivation (`impex`, `Impex`).
- With `impex.authorization` on, the Atrium screens now authorize every page and action against `impex.policies` (403 when refused), list and count only the runs the user owns (and their messages) as the API does, and make the user who starts a flow from the dashboard its owner. Previously every Atrium user could see and act on every run. Turn `impex.authorization` off, or register your own policies, for an operator dashboard.
- Status colours: running is now `primary` and pending/waiting `info`; inbound messages are `neutral` rather than `info`.

### Fixed

- The channels screen read a `signed` key the action never returns, so every channel showed as unsigned; it now reads `verifies_signatures`.

- Cortex integration: when `refactor-circus/cortex` is installed, the MCP server registers with it as `impex` and every tool joins its tool registry (tagged `impex`), so agents can use them. Published instruction and tool description overrides are served to MCP clients and agents. Configured under `impex.cortex`; Cortex stays optional.
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
- `RefactorCircus\Impex\Testing\Flows` assertion helpers

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
