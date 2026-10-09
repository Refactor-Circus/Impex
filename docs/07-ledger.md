# The ledger

Every payload that crosses the application boundary becomes a row in
`impex_messages`, in both directions: one row per crossing, with its channel,
endpoint, status, duration, size and a sha256 of the body, linked to the run,
step or subscription delivery that caused it. A crossing that failed is
recorded too — a timeout or a refused connection is exactly the one someone
opens the ledger to find.

Channels, transports and `Impex::send()` are covered in
[Channels and transports](18-channels.md). This page is about what gets
recorded, and receiving.

## How a row is written

`MessageRecorder` writes each row with a single query-builder insert, not
through Eloquent. This is the hottest table in an application that sends a
million deliveries a day, so no `MessageModel` lifecycle events fire per row;
only `MessageRecorded` does, carrying the new row's id.

### Bodies

| Body | Where it goes |
|---|---|
| Up to `impex.artifacts.inline_threshold` | Whole, in the row's `body` column |
| Larger | The artifact disk, referenced by `body_artifact_id`, so a 40MB supplier feed does not land in a column |
| Binary (not valid UTF-8) | Base64'd first, with `body_encoding: base64` — a text column and the artifact's JSON envelope both need valid UTF-8 |
| A stream, or an outbound HTTP body above `impex.messages.max_recorded_bytes` or that cannot be rewound | Not kept: recorded by size alone, so recording never holds a whole feed in memory |

`body_preview` keeps the first `impex.messages.preview_bytes` of a text body
for list views. `body_sha256` and `bytes` are kept **whatever happens to the
body**, so a delivery can be matched to what the other side says it received.

```php
Impex::body($message);       // the full body, from the column or the disk, decoded
$message->body_preview;      // the first 2KB, for a list view
```

`Impex::body()` answers `null` when the body was not kept. The raw `body`
column is hidden from serialization; read it through `Impex::body()`, which
decodes base64.

### Body policy

Whether a body is kept at all is the channel's `body_policy`: `all` (the
default), `failures` (an error, or a status of 400 or above) or `none`. A
channel pushing a million product updates a day can keep only the failures,
whose bodies are the ones anybody opens. Traffic on a name no channel defines
keeps everything. See [Channels](18-channels.md#body-policy).

### Headers

Only the headers a channel names in `store_headers` are stored. Webhook headers
routinely carry bearer tokens and signatures, and the dashboard renders this
table. Use `['*']` to store everything, deliberately.

## Inbound: channels

An inbound channel is a named way in.

```php
// config/impex.php
'channels' => [
    'supplier-feed' => [
        'direction' => 'inbound',
        'signing_secret' => env('IMPEX_SUPPLIER_SECRET'),
        'signature_header' => 'X-Signature',
        'signature_validator' => \RefactorCircus\Impex\Domains\Channel\Support\HmacSha256Validator::class,
        'profile' => \RefactorCircus\Impex\Domains\Channel\Support\ProcessEverything::class,
        'idempotency_header' => 'X-Request-Id',
        'flow' => 'extract-products',
        'store_headers' => ['content-type', 'x-request-id'],
        'path' => null,   // defaults to channels/supplier-feed
    ],
],
```

`POST /impex/channels/supplier-feed` then:

1. validates the signature,
2. records the request in the ledger,
3. applies the profile,
4. responds `202` immediately,
5. queues the bound flow with the decoded JSON body as its first argument.

```json
{ "message": "Accepted.", "message_id": "01JQ…", "run_id": "01JQ…" }
```

A channel with no `flow` answers `202` with only `message_id`. Channels stored
at runtime receive on the same generic endpoint. An outbound or disabled
channel answers `404`.

### A rejected request is still recorded

A bad signature returns `403` and starts no run — but the message row is written
first, with `signature_valid: false`. It is evidence of what an upstream sent,
which is exactly what a ledger is for.

### Idempotency

`unique(channel, idempotency_key)` — a plain index cannot dedupe concurrent
redelivery, because both requests pass the `SELECT` and both insert. Scoped to
the channel because two suppliers may legitimately reuse a key. The recorder
uses `insertOrIgnore` against that index, so of two concurrent redeliveries one
writes and the other reads the winner's row back instead of failing.

Set `idempotency_header` and a redelivered webhook returns the original message
and run instead of starting a second one. The run is started under the same key,
and a run's key is unique per flow.

### Signature validation

Two validators ship, `HmacSha256Validator` (the default) and
`StandardWebhooksValidator`, and both accept any of the channel's secrets so a
sender can rotate without a gap. See
[Channels](18-channels.md#inbound). Every other upstream signs differently:

```php
namespace App\Impex;

use Illuminate\Http\Request;
use RefactorCircus\Impex\Domains\Channel\Contracts\SignatureValidator;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;

final class StripeSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, ChannelConfig $config): bool
    {
        $header = (string) $request->header($config->signatureHeader);

        [$timestamp, $signature] = $this->parse($header);

        foreach ($config->secrets() as $secret) {
            $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

            // Constant-time: a timing-variable comparison leaks the signature
            // one byte at a time.
            if (hash_equals($expected, $signature)) {
                return abs(time() - (int) $timestamp) < 300;
            }
        }

        return false;
    }
}
```

```php
'signature_validator' => \App\Impex\StripeSignatureValidator::class,
```

Fail closed: a channel with no secret should accept nothing.

### Filtering with a profile

```php
namespace App\Impex;

use Illuminate\Http\Request;
use RefactorCircus\Impex\Domains\Channel\Contracts\ChannelProfile;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;

final class OnlyProductEvents implements ChannelProfile
{
    public function shouldProcess(Request $request, ChannelConfig $config): bool
    {
        return str_starts_with((string) $request->json('type'), 'product.');
    }
}
```

A request the profile rejects is still recorded and still answered `202`
(`Accepted, not processed.`). It simply starts no run.

## Outbound

Send through an outbound channel and the transport records the crossing,
success or failure:

```php
$receipt = Impex::send('carrier-api', ['skus' => $skus]);
```

See [Channels](18-channels.md#outbound). Two lower-level tools remain for
traffic that does not go through a channel.

`Impex::http()` returns a `PendingRequest` with a recorder middleware attached,
so every call inside an action lands in the ledger with its run and step,
timing, and the request headers the channel names in `store_headers`. A call
that never got a response — a timeout, a refused connection — is recorded with
its error too.

```php
final class FetchPricing
{
    public function __construct(private readonly Impex $impex) {}

    public function execute(array $skus, string $runId): array
    {
        return $this->impex->http('vendor-api', $runId)
            ->post('https://vendor.test/quote', ['skus' => $skus])
            ->json();
    }
}
```

For egress neither can see — a message published to another system by its own
client:

```php
Impex::record(
    channel: 'sftp-drop',
    endpoint: 'sftp://partner.test/in/catalogue.csv',
    body: $csv,
    transport: 'file',
    runId: $runId,
);
```

Mail is recorded by wrapping the application's mailer in the `impex` mail
transport. See [Channels](18-channels.md#mail).

## Reading the ledger

```php
use RefactorCircus\Impex\Domains\Message\Enums\Direction;

MessageModel::query()->where('direction', Direction::Inbound)->latest('occurred_at')->get();
MessageModel::query()->where('delivery_id', $deliveryId)->first();   // what a subscription delivery sent

$run->messages;                       // everything this run caused
```

```
GET impex/messages?direction=inbound&channel=supplier-feed
GET impex/messages/{message}
```

## Retention

```php
'retention' => [
    'messages_days' => 90,
    'artifacts_days' => 365,   // never shorter than what points at it
],
```

Artifacts must never expire before the rows referencing them, or a failed run
loses the payloads you would open it to read. `impex:prune` deletes in
dependency order.
