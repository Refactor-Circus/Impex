# Installation

```bash
composer require refactor-circus/impex
php artisan vendor:publish --tag=impex-config
php artisan vendor:publish --tag=impex-migrations
php artisan migrate
```

Impex needs PHP 8.4 and Laravel `^13.34`. Install the `pcntl` extension on
your queue workers: it is what lets a worker time a job out, and since 13.34
Laravel tells the job first, so a batch item about to be killed gives itself up
for an immediate retry. See [Scale](06-scale.md#batch-items-whose-worker-dies).

## The scheduled sweep is not optional

```php
// routes/console.php, or bootstrap/app.php
Schedule::command('impex:tick')->everyMinute();
```

The package registers this itself when `impex.timers.enabled` is not `false`, so
you only need to add it manually if you have disabled that.

**Without it, a run that sleeps or waits on a signal for longer than the queue's
delay ceiling never wakes.** `impex:tick` is also the repair pass: it reclaims
steps and batch items whose invocation was killed mid-flight, finalises
batches whose completion check was throttled, queues subscription detection a
lost job never ran, and retries subscription deliveries once their backoff has
elapsed.

## Schedule pruning

`impex:prune` is not scheduled for you. Run it daily:

```php
Schedule::command('impex:prune')->daily();
```

It deletes, in dependency order, runs, ledger messages, subscription events,
delivery attempts and artifacts past their `impex.retention` windows. Without
it the ledger and the event sequence grow forever.

## Publish tags

| Tag | Contents |
|---|---|
| `impex-config` | `config/impex.php` |
| `impex-migrations` | the eight migrations |
| `impex-views` | the dashboard's Blade views, if you want to customise them |
| `impex-lang` | translation strings |
| `impex` | all of the above |

The dashboard's stylesheet and scripts belong to Atrium and are published with
its own `atrium-assets` tag. See [Dashboard](11-dashboard.md).

## Recording mail

To record every mail the application sends, wrap its real mailer in the
`impex` mail transport and make that the default:

```php
// config/mail.php
'default' => env('MAIL_MAILER', 'impex'),

'mailers' => [
    'impex' => ['transport' => 'impex', 'mailer' => 'ses', 'channel' => 'mail'],
    'ses' => ['transport' => 'ses'],
],
```

Mail still goes out through `ses`; each one, failures included, is also a row
in the ledger on the `mail` channel. If you cannot switch the default mailer,
set `impex.mail.record_unwrapped` to record sent mail from any mailer instead
(successes only). See [Channels and transports](18-channels.md#mail).

## The subscriber API

If subscribers will manage their own subscriptions, put your OAuth middleware
on their stack before exposing it:

```php
// config/impex.php
'routes' => [
    'subscriber_middleware' => ['api', 'auth:api'],   // or ['api', 'client:impex']
],
```

Each subscriber is then found by its token's OAuth client, matched to a
subscriber's `client_id`. See [Subscriptions](19-subscriptions.md#the-subscriber-api).

## Vapor checklist

Impex is built for a runtime with a hard execution ceiling, a small queue
message, no local disk and no shared memory. Five settings make that work:

```php
// config/impex.php
'artifacts' => [
    'disk' => 's3',            // Lambda has no persistent local filesystem
],

'cache' => [
    'store' => 'dynamodb',     // locks must be shared across invocations
],

'limits' => [
    'max_step_seconds' => 840, // below Lambda's 900s ceiling
    'lease_seconds' => 900,    // must exceed max_step_seconds
],

'queue' => [
    'connection' => 'sqs',
],
```

`lease_seconds` **must** stay above `max_step_seconds`. If it does not, a
legitimately slow step has its lease reclaimed while it is still working, and
runs twice.

### Why each one

| Constraint | What the package does | What you must set |
|---|---|---|
| 900s execution ceiling | one step per invocation; long steps checkpoint and resume | `limits.max_step_seconds` |
| 256KB SQS message limit | jobs carry ULIDs only; payloads above the threshold go to a disk | `artifacts.disk` |
| 15-minute SQS delay cap | long waits become timer rows swept by `impex:tick` | the schedule entry |
| at-least-once delivery | steps and batch items are leased before they execute | `limits.lease_seconds` |
| no shared memory | drives are serialised with a cache lock | `cache.store` |

A cache store that cannot take atomic locks raises an exception naming the store
rather than silently allowing two invocations to replay the same run.

## Verifying the install

```bash
php artisan impex:run --help
php artisan impex:tick
```

A first flow, end to end:

```php
// app/Flows/PingFlow.php
final class PingFlow extends \RefactorCircus\Impex\Domains\Flow\Support\Flow
{
    public function handle(string $message): array
    {
        return $this->action(\App\Flows\Actions\Echo::class, $message)->run();
    }
}

// app/Flows/Actions/Echo.php
final class Echo
{
    public function execute(string $message): array
    {
        return ['echoed' => $message];
    }
}

// config/impex.php
'flows' => ['ping' => \App\Flows\PingFlow::class],
```

```bash
php artisan impex:run ping --argument="hello"
php artisan queue:work --once
```
