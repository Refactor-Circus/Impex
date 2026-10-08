# Extending

## Registering flows from another package

`Impex::flows()->register()` is safe to call from any service provider's
`boot()`, in any order.

```php
namespace VendorName\Catalogue;

use Illuminate\Support\ServiceProvider;
use JayI\Impex\Domains\Flow\Services\FlowRegistry;

final class CatalogueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // callAfterResolving means this works whether Impex booted before or
        // after this provider — the closure runs when the registry is built.
        $this->callAfterResolving(FlowRegistry::class, function (FlowRegistry $flows): void {
            $flows->registerMany([
                'catalogue:sync' => SyncCatalogueFlow::class,
                'catalogue:reindex' => ReindexFlow::class,
            ]);
        });
    }
}
```

### Precedence

**The application's config always wins over a package's registration**, and this
does not depend on boot order. An application can point a slug a package ships
at its own subclass:

```php
// config/impex.php
'flows' => [
    'catalogue:sync' => \App\Flows\OurSyncFlow::class,   // wins
],
```

The package's own view stays readable, which is what makes the override visible
rather than mysterious:

```php
Impex::flows()->registered()['catalogue:sync'];   // the package's class
Impex::flows()->class('catalogue:sync');          // the app's class
```

### Collisions

Two packages claiming the same slug is an **error**, not a silent shadowing:

```
JayI\Impex\Domains\Flow\Exceptions\FlowCollisionException

  The flow slug [sync] is already registered to [VendorA\SyncFlow], and
  [VendorB\SyncFlow] tried to claim it. Prefix the slug with the package name,
  or set `impex.flows.sync` in the application config to choose explicitly —
  config always wins over a package registration.
```

Prefix your slugs (`catalogue:sync`) and this never comes up. Registering the
same class under the same slug twice is a no-op, so a provider that boots more
than once does no harm.

## Swapping engine internals

The engine is a facade over collaborators, each resolved from the container. To
change one, bind your own — no forking, no subclassing the engine.

| Bind | To change |
|---|---|
| `RollbackStrategy` | What a failed run unwinds, and in what order |
| `StepWriter` | How steps are recorded — extra columns, a different payload policy |
| `JobRouter` | How engine jobs are routed onto queues |
| `EngineOptions` | Where the tuning comes from, if not config |
| `Waits` | Signal delivery, sleeps, timer firing, deadline enforcement |
| `Children` | How child runs are started and reported back |
| `Sweeper` | What the periodic pass does |

```php
// A strategy that unwinds only as far as a marked step.
final class UnwindToCheckpoint implements RollbackStrategy
{
    public function __construct(private readonly Rollbacks $default) {}

    public function next(RunModel $run): bool
    {
        if ($run->tags['checkpointed'] ?? false) {
            return false;   // nothing to undo past the checkpoint
        }

        return $this->default->next($run);
    }

    public function halts(RunStepModel $rollbackStep): bool
    {
        return $this->default->halts($rollbackStep);
    }
}
```

```php
// A service provider
$this->app->bind(RollbackStrategy::class, UnwindToCheckpoint::class);
```

Decorating the shipped implementation, as above, is usually better than
replacing it — the default carries the parts that are easy to get wrong, like
settling a failed rollback so the unwind cannot loop.

### The sweep

`Sweeper` is a class rather than logic inside `impex:tick`, so you can call it
from a job or a health check:

```php
$report = app(Sweeper::class)->sweep();

$report->idle();          // nothing to do
$report->timers;          // fired
$report->leases;          // step and batch item leases reclaimed
$report->expiredSteps;    // past their deadline
$report->detections;      // streams queued for change detection
$report->deliveries;      // subscriptions queued for delivery
$report->summary();       // one line, for a log or a command
```

### Tuning

`EngineOptions` reads the config once and validates it. `lease_seconds` at or
below `max_step_seconds` throws with both values named, rather than surfacing
later as a slow step that ran twice.

## Contracts

Each has a real second implementation or a real host-app need. Everything else
is a concrete class, and becomes a contract when a second implementation
actually exists.

| Contract | Purpose |
|---|---|
| `Channel\Contracts\SignatureValidator` | Verifying an inbound request — every upstream signs differently |
| `Channel\Contracts\ChannelProfile` | Deciding which inbound requests become runs |
| `Channel\Contracts\Transport` | How bytes leave: http, mail, file, or yours |
| `Channel\Contracts\Signer` | Signing an outbound body |
| `Subscription\Contracts\Stream` | A source of change subscribers follow (via `AbstractStream`) |
| `Subscription\Contracts\SubscriptionMatcher` | Which filtered subscriptions a changed subject falls into |
| `Subscription\Contracts\Formatter` | What a subscriber receives for a batch of events |
| `Subscription\Contracts\ResolvesSubscriber` | Which subscriber a subscriber-API request is |
| `JayI\Impex\Contracts\JobMiddlewareFactory` | Queue middleware built for the job in hand |
| `BatchSource` | Streaming resumable pages of work into a batch |
| `RollbackStrategy` | What a failed run unwinds, and in what order |
| `Resumable` | An action that checkpoints and resumes (via `ResumableAction`) |

The domain contracts live under `JayI\Impex\Domains\…`. Most are resolved at the
point of use from a config value, so swapping one is a config change:

```php
'channels' => [
    'stripe' => ['signature_validator' => \App\Impex\StripeSignatureValidator::class],
],
```

## Registering streams from a package

A package that owns data registers its stream, and reports change; Impex does
the rest. See [Subscriptions](19-subscriptions.md#writing-a-stream) for writing
one.

```php
use JayI\Impex\Domains\Subscription\Services\StreamRegistry;

public function boot(): void
{
    // Runs whenever the registry is built, before or after this provider.
    $this->callAfterResolving(StreamRegistry::class, function (StreamRegistry $streams): void {
        $streams->register(ProductStream::class);
    });

    Product::saved(fn (Product $product) => Impex::streams()->touch('catalogue.products', [$product->sku]));
    Product::deleted(fn (Product $product) => Impex::streams()->touch('catalogue.products', [$product->sku]));
}
```

`Impex::streams()->register()` does the same directly. A stream is keyed by
`key()`; registering another under the same key replaces it. An application can
also list stream classes in `impex.streams`. Prefix keys with the package
(`catalogue.products`), because the key is stored on every event and
subscription and never changes.

## Custom transports

```php
use JayI\Impex\Domains\Channel\Contracts\Transport;
use JayI\Impex\Domains\Channel\Data\ChannelConfig;
use JayI\Impex\Domains\Channel\Data\OutboundMessage;
use JayI\Impex\Domains\Channel\Data\Receipt;
use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Message\Services\MessageRecorder;
use Throwable;

final class SqsTransport implements Transport
{
    public function __construct(
        private readonly SqsClient $sqs,
        private readonly MessageRecorder $recorder,
    ) {}

    public function send(ChannelConfig $channel, OutboundMessage $message): Receipt
    {
        $queue = $message->endpoint ?? (string) $channel->option('queue_url');
        $startedAt = microtime(true);
        $error = null;

        try {
            $this->sqs->sendMessage(['QueueUrl' => $queue, 'MessageBody' => (string) $message->body]);
        } catch (Throwable $e) {
            $error = ['message' => $e->getMessage(), 'class' => $e::class];
        }

        // Record success and failure alike: the ledger answers "did we send it?".
        $recorded = $this->recorder->record(
            direction: Direction::Outbound,
            channel: $channel->name,
            transport: 'sqs',
            endpoint: $queue,
            body: $message->body,
            runId: $message->link('run_id'),
            stepId: $message->link('step_id'),
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            error: $error,
            deliveryId: $message->link('delivery_id'),
        );

        return new Receipt(successful: $error === null, messageId: (string) $recorded->getKey(), error: $error);
    }
}
```

```php
// A service provider's boot()
Impex::transports()->extend('sqs', fn ($app) => $app->make(SqsTransport::class));
```

```php
'order-bus' => ['direction' => 'outbound', 'transport' => 'sqs', 'options' => ['queue_url' => env('ORDER_BUS_URL')]],
```

Return a failed receipt rather than throwing for a send that did not go
through; throw only when the channel cannot send at all. The recorder applies
the channel's body policy for you.

## Signers and signature validators

An outbound signer implements `Signer::sign(ChannelConfig $channel, string $messageId, int $timestamp, string $body): array`
and returns the headers to add. Use it for one channel with `options.signer`,
or for every channel with `impex.outbound.signer`. It is called only for HTTP
sends through a channel with a secret; sign with `$channel->secrets()` — every
secret during a rotation, or the first, the current one.

An inbound validator implements `SignatureValidator::isValid(Request $request, ChannelConfig $config): bool`
and is named in the channel's `signature_validator`. Fail closed when
`$config->verifiesSignatures()` is false, and accept any of `$config->secrets()`
so senders can rotate. See [The ledger](07-ledger.md#signature-validation).

## Resolving subscribers

The subscriber API leaves authentication to the application's middleware and
asks a `ResolvesSubscriber` which subscriber the request is. The default maps
the token's OAuth client to a subscriber's `client_id`. To map signed-in users
instead:

```php
use Illuminate\Http\Request;
use JayI\Impex\Domains\Subscription\Contracts\ResolvesSubscriber;
use JayI\Impex\Domains\Subscription\Enums\SubscriberStatus;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

final class VendorUserResolver implements ResolvesSubscriber
{
    public function resolve(Request $request): ?SubscriberModel
    {
        $vendor = $request->user()?->vendor;

        return $vendor === null ? null : SubscriberModel::query()
            ->where('owner_type', $vendor->getMorphClass())
            ->where('owner_id', (string) $vendor->getKey())
            ->where('status', SubscriberStatus::Active)
            ->first();
    }
}
```

```php
// config/impex.php
'subscriptions' => ['resolver' => \App\Impex\VendorUserResolver::class],
```

Return `null` and the request is refused with `403`.

## Job middleware

Every Impex job — `DriveRun`, `ExecuteStep`, `SeedBatch`, `ProcessBatchItem`,
`DetectStream`, `DeliverSubscription`, `ExportSubscription`, all in
`JayI\Impex\Jobs` — reads its queue middleware from `impex.jobs.middleware`
when it runs: entries under `*` for every job, then those under the job's class.

```php
'jobs' => [
    'middleware' => [
        '*' => [\App\Queue\TagWithTenant::class],
        \JayI\Impex\Jobs\DeliverSubscription::class => [
            [\Illuminate\Queue\Middleware\RateLimited::class, 'impex-deliveries'],
        ],
    ],
],
```

An entry is a middleware class (resolved from the container), a list of a class
and its constructor arguments, or a `JobMiddlewareFactory`. Classes and arrays
only, so the config still caches. A factory builds middleware from the job
itself — a lock keyed by the subscription a delivery is for, say:

```php
use Illuminate\Queue\Middleware\WithoutOverlapping;
use JayI\Impex\Contracts\JobMiddlewareFactory;
use JayI\Impex\Jobs\DeliverSubscription;

final class OneDeliveryPerSubscriber implements JobMiddlewareFactory
{
    public function middleware(object $job): array
    {
        return $job instanceof DeliverSubscription
            ? [(new WithoutOverlapping('impex-delivery:'.$job->subscriptionId))->releaseAfter(30)]
            : [];
    }
}
```

```php
'jobs' => ['middleware' => [DeliverSubscription::class => [OneDeliveryPerSubscriber::class]]],
```

## Events

Every state transition emits one.

| Event | Payload |
|---|---|
| `RunStarted` | `$runId` |
| `RunCompleted` | `$runId` |
| `RunFailed` | `$runId` |
| `StepCompleted` | `$runId`, `$stepId` |
| `StepFailed` | `$runId`, `$stepId` |
| `MessageRecorded` | `$messageId` — once per ledger row, the only event the hot recording path fires |
| `SubscriptionDisabled` | `$subscriptionId` — the circuit breaker switched a failing subscription off |

```php
use Illuminate\Support\Facades\Event;
use JayI\Impex\Domains\Run\Events\RunFailed;
use JayI\Impex\Domains\Run\Models\RunModel;

Event::listen(function (RunFailed $event): void {
    $run = RunModel::query()->find($event->runId);

    Log::error('Impex run failed', [
        'run' => $event->runId,
        'flow' => $run?->flow,
        'error' => $run?->error,
    ]);
});
```

Events carry identifiers, not models, so a listener queued onto SQS stays well
inside the message limit.

Listen for `SubscriptionDisabled` to tell a subscriber their endpoint has been
failing — the subscription stays off, collecting events, until someone resumes
it:

```php
use JayI\Impex\Domains\Subscription\Events\SubscriptionDisabled;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

Event::listen(function (SubscriptionDisabled $event): void {
    $subscription = SubscriptionModel::query()->with('subscriber')->find($event->subscriptionId);

    Notification::route('mail', $subscription?->subscriber->metadata['contact'] ?? null)
        ->notify(new EndpointFailing($subscription));
});
```

Alongside these engine events, every model fires a class-based event per
Eloquent hook (`JayI\Impex\Domains\Run\Events\RunCreatedEvent`, ...) and every
action fires a start and a finish event (`JayI\Impex\Domains\Flow\Events\FlowRanActionEvent`,
...). Listen to `ModelLifecycleEvent`, `ActionStartingEvent` or
`ActionFinishedEvent` in `JayI\Foundation\Contracts` to receive a whole family. See
the [README](../README.md#events) for the full list.

## Registering channels from a package

Channels come from config, so a package merges its own:

```php
public function register(): void
{
    $this->mergeConfigFrom(__DIR__.'/../config/catalogue-channels.php', 'impex.channels');
}
```

Note `mergeConfigFrom` is shallow — an application redefining the same channel
key replaces it wholesale, which is usually what you want. A channel stored at
runtime under the same name wins over both. See
[Channels and transports](18-channels.md).

## Custom artifact storage

Point the disk at any Flysystem adapter:

```php
'artifacts' => ['disk' => 'supplier-archive', 'path' => 'impex', 'inline_threshold' => 65536],
```

## Swapping the queue per flow

```php
$run = Impex::run('extract-products', [$query]);

$run->update(['queue_connection' => 'sqs-slow', 'queue' => 'impex-bulk']);
```

Every job the run dispatches after that inherits the routing.
