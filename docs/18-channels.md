# Channels and transports

A channel is a named boundary: a way in, or a way out. Everything the
application receives or sends goes through one, and every crossing is a row in
the [ledger](07-ledger.md) under the channel's name. That is what makes Impex
the application's I/O gateway rather than a log you remember to write to.

## Where channels come from

Two sources, one catalogue:

- **Configured** in `impex.channels`. Code is the source of truth for the
  channels the application itself owns.
- **Stored** in `impex_channels` (`ChannelModel`), created at runtime through
  the [API](09-api.md#channels), MCP or a subscription — a subscriber's webhook
  endpoint, an integration an operator adds.

A stored channel of the same name **wins** over a configured one. The API
refuses to create a stored channel under a configured name, though, because it
would silently shadow it; to change a configured channel, change the config.

```php
Impex::channels()->all();          // configured and stored, by name
Impex::channels()->configured();   // config only
Impex::channels()->inbound();      // or outbound()
Impex::channels()->get('carrier-api');   // throws UnknownChannelException
Impex::channels()->find('carrier-api');  // or null
```

### Caching

Stored channels are read from the database at most once per
`impex.channel_registry.cache_seconds` (30) in each process. A worker sending a
million deliveries resolves the same few channels over and over; reading them
once per window keeps that off the database. A change made in a process shows
there at once (the registry is flushed whenever a channel is saved or deleted),
and in other processes once their copy ages out.

The route file reads configured channels only, so registering routes never
touches the database. Stored inbound channels are served by the generic
`POST impex/channels/{channel}` endpoint.

## Channel keys

| Key | Default | Meaning |
|---|---|---|
| `direction` | `inbound` | `inbound` or `outbound`. Fixed once traffic has crossed it. |
| `transport` | `http` | `http`, `mail`, `file`, or a name registered with `Impex::transports()->extend()`. |
| `status` | `active` | `active` or `disabled`. A disabled channel neither sends nor receives. |
| `body_policy` | `all` | Which bodies the ledger keeps: `all`, `failures` or `none`. |
| `options` | `[]` | Transport settings — a URL, a mailer, a disk. See below. |
| `credentials` | `[]` | Secrets: `signing_secret`, and older `secrets` still accepted. Encrypted at rest when stored; never returned. |
| `signing_secret` | `null` | Shorthand for `credentials.signing_secret` in config. |
| `signature_header` | `X-Signature` | Header the HMAC validator and signer use. |
| `signature_validator` | `HmacSha256Validator` | Inbound: how a request proves its sender. |
| `profile` | `ProcessEverything` | Inbound: which requests start a run. |
| `flow` | `null` | Inbound: the flow a request starts. |
| `idempotency_header` | `null` | Inbound: header carrying the sender's dedupe key. |
| `store_headers` | `[]` | Headers kept in the ledger. `['*']` keeps everything, deliberately. |
| `queue` | `null` | Reserved; not read by the engine. |
| `path` | `null` | Inbound, configured only: a custom path, default `channels/{name}`. |

A stored channel keeps its inbound settings (`signature_header`,
`signature_validator`, `profile`, `flow`, `store_headers`,
`idempotency_header`) inside `options`, and its secrets in `credentials`.

## Inbound

Unchanged in shape: see [The ledger](07-ledger.md#inbound-channels). An outbound
or disabled channel answers `404` on the receive endpoint, saying no more about
itself than a name nobody configured.

Two validators ship. Both **fail closed**: a channel with no secret accepts
nothing.

| Validator | Scheme |
|---|---|
| `HmacSha256Validator` (default) | Hex HMAC-SHA256 of the raw body in `signature_header`. |
| `StandardWebhooksValidator` | [Standard Webhooks](https://www.standardwebhooks.com): `webhook-id`, `webhook-timestamp`, `webhook-signature`, signed over `{id}.{timestamp}.{body}`. A timestamp outside `options.tolerance_seconds` (300) is refused even with a good signature: it is a replay, or a clock wrong enough that the sender should hear about it. |

```php
'partner-hooks' => [
    'direction' => 'inbound',
    'credentials' => ['signing_secret' => env('PARTNER_SECRET')],
    'signature_validator' => \JayI\Impex\Domains\Channel\Support\StandardWebhooksValidator::class,
    'options' => ['tolerance_seconds' => 600],
    'flow' => 'partner-event',
],
```

Both accept **any** of the channel's secrets — `signing_secret` and every entry
in `credentials.secrets` — so a sender can rotate without a window in which its
deliveries are refused.

## Outbound

```php
use JayI\Impex\Domains\Channel\Data\OutboundMessage;

$receipt = Impex::send('carrier-api', ['zip' => '90210']);            // an array is JSON
$receipt = Impex::send('carrier-api', $rawXml);                        // a string as it is
$receipt = Impex::send('ops-mail', OutboundMessage::mail(new LowStockMail($sku)));
$receipt = Impex::send('partner-drop', new OutboundMessage(stream: $handle));
```

`Impex::send()` resolves the channel, picks its transport, sends, and records
the crossing. It returns a `Receipt`:

| Property | Meaning |
|---|---|
| `successful` | Whether the other side took it: a 2xx, a mail handed over, a file written. `failed()` is the inverse. |
| `messageId` | The ledger row that records it. |
| `statusCode` | HTTP only. |
| `durationMs` | |
| `error` | `{message, class}` or `{message, response}`, when it failed. |
| `response` | HTTP only: the first `impex.messages.preview_bytes` of the response body. |

**A failed send is a failed receipt, not an exception.** A 500, a timeout, a
refused connection and a refused endpoint are all recorded and returned, so the
caller decides what a failure means — a subscription delivery backs off, a flow
action might throw to retry. What does throw is a channel that cannot send at
all: an inbound one, a disabled one, an unknown transport, or a missing
required option (`ChannelUnavailableException`, a `409` over HTTP).

### The message

`OutboundMessage` carries what to send; the channel supplies where and how.
Anything set on the message wins over the channel's options, so one channel can
serve several paths of the same upstream.

| Field | Meaning |
|---|---|
| `body` | The encoded body, exactly as sent. |
| `stream` | A resource, for a body too large to hold in memory. Recorded by size only. |
| `headers` | Added to the channel's `options.headers`. |
| `endpoint` | A URL, a path on the disk, or mail recipients. |
| `method` | HTTP method. |
| `mailable` | A `Mailable`, for a mail channel. |
| `id` | The message id; for HTTP it is the `webhook-id` the signer uses. |
| `idempotencyKey` | |
| `links` | `run_id`, `step_id`, `delivery_id`: what the ledger row is linked to. |

`OutboundMessage::json($data, headers:, endpoint:, method:, id:, idempotencyKey:, links:)`
encodes a JSON body and sets `Content-Type`. `OutboundMessage::mail($mailable, to:, id:, links:)`
wraps a mailable. Pass `links: ['run_id' => $runId, 'step_id' => $stepId]` from
an action so the row is traceable to the work that made it.

### Transports

| Transport | Options |
|---|---|
| `http` | `url`, `method` (`POST`), `headers`, `timeout`, `connect_timeout`, `gzip`, `signer`, `guard` |
| `mail` | `mailer` (the default mailer when unset), `to` (recipients when the message names none), `subject` (for a plain body) |
| `file` | `disk` (the default disk when unset), `path`, which may use `{id}`, `{date}` and `{time}` |

```php
'channels' => [
    'carrier-api' => [
        'direction' => 'outbound',
        'transport' => 'http',
        'options' => ['url' => 'https://api.carrier.test/rates', 'timeout' => 5],
        'credentials' => ['signing_secret' => env('CARRIER_SECRET')],
        'body_policy' => 'failures',
    ],
    'ops-mail' => [
        'direction' => 'outbound',
        'transport' => 'mail',
        'options' => ['to' => 'ops@example.com', 'mailer' => 'ses'],
    ],
    'partner-drop' => [
        'direction' => 'outbound',
        'transport' => 'file',
        'options' => ['disk' => 'partner-sftp', 'path' => 'in/catalogue-{date}-{id}.csv'],
    ],
],
```

HTTP `timeout` and `connect_timeout` fall back to `impex.outbound.timeout` (10)
and `impex.outbound.connect_timeout` (3). With `gzip` true a body is compressed
and sent with `Content-Encoding: gzip`; a streamed body never is.

A mail channel records the raw MIME message it sent. A file channel records the
endpoint as `{disk}://{path}`.

### Custom transports

`TransportManager` is a Laravel `Manager`. Register a transport by name and
point a channel's `transport` at it:

```php
use JayI\Impex\Domains\Channel\Contracts\Transport;

Impex::transports()->extend('sqs', fn ($app) => $app->make(SqsTransport::class));
```

A transport implements `Transport::send(ChannelConfig $channel, OutboundMessage $message): Receipt`,
and **records its own crossing** through `MessageRecorder::record()`, success or
failure, so the ledger stays the one place to answer "did we send it?". See
[Extending](12-extending.md#custom-transports).

## Body policy

Every crossing is recorded with its size and a sha256 of the body. The body
policy decides only whether the **body itself** is kept:

| Policy | Keeps |
|---|---|
| `all` | every body (the default) |
| `failures` | bodies of failed crossings: an error, or a status of 400 or above |
| `none` | no bodies |

A channel pushing a million product updates a day can keep only the failures,
whose bodies are the ones anybody opens; the sha256 still lets you match a
delivery to what the other side says it received. Traffic on a name no channel
defines — an ad hoc label passed to `Impex::http()` — keeps everything.

## Signing

An HTTP send through a channel that has a secret is signed. The signer is the
channel's `options.signer`, else `impex.outbound.signer`; set the latter to
`null` to sign nothing.

| Signer | Headers |
|---|---|
| `StandardWebhooksSigner` (default) | `webhook-id`, `webhook-timestamp`, `webhook-signature` — `v1,{base64 HMAC-SHA256 of "{id}.{timestamp}.{body}"}`, once per secret, space-separated |
| `HmacSha256Signer` | The hex HMAC-SHA256 of the body under the current secret, in `signature_header` — the mirror of `HmacSha256Validator` |

A `whsec_`-prefixed secret is the Standard Webhooks base64 encoding of the key
and is decoded first; any other secret is used as it is. Signing under the id
and timestamp means a captured delivery cannot be replayed under a new id or
after the receiver's tolerance window. Write your own by implementing
`JayI\Impex\Domains\Channel\Contracts\Signer`.

### Rotating a secret

```
POST impex/channels/{channel}/rotate-secret
```

`RotateChannelSecretAction` gives a **stored** channel a new `whsec_` secret
and keeps the previous one in `credentials.secrets`. The new secret is in the
response once and never again. Outbound, every secret signs, so a receiver
verifying with either keeps accepting deliveries while it switches. Inbound,
either verifies. The next rotation drops the oldest. A configured channel
rotates in config: put the old secret in `credentials.secrets` and the new one
in `signing_secret`.

## The endpoint guard

A subscriber registers a URL and the application calls it. Without a guard, a
URL resolving to `169.254.169.254` or `10.0.0.5` turns every delivery into a
request from inside your network (SSRF). `EndpointGuard`:

1. requires `https` (plain `http` only with `impex.outbound.allow_insecure`),
2. resolves the host once and refuses any private or reserved address,
3. pins the request to the address that passed (`CURLOPT_RESOLVE`), so a DNS
   answer that changes between the check and the call cannot slip past it.

It applies to HTTP sends through a channel **someone outside the application
supplied** — one with an owner, such as a subscription's endpoint — and to any
channel whose `options.guard` is `true`. An owned channel's URL is also checked
when it is created or changed, so a bad URL is refused (`422`) to the person
entering it rather than discovered at the first delivery. With
`impex.authorization` on, a channel created through the API or MCP belongs to
the user who created it, so it is guarded too.

A guarded send to an unsafe endpoint is recorded and returned as a failed
receipt, like any other send that could not go out. Leave `impex.outbound.guard`
on outside tests; a test suite talking to `Http::fake()` hosts turns it off.

## Mail

The `mail` transport sends through Impex. To record **every** mail the
application sends — Mailables, notifications, password resets — wrap the real
mailer in the `impex` mail transport and make it the default:

```php
// config/mail.php
'default' => 'impex',

'mailers' => [
    'impex' => ['transport' => 'impex', 'mailer' => 'ses', 'channel' => 'mail'],
    'ses' => ['transport' => 'ses'],
],
```

`RecordingMailTransport` hands each message to the mailer named in `mailer`
and records it on `channel` (default `impex.mail.channel`, `mail`). A send that
fails is recorded with its error and the exception rethrown, so the
application sees the failure exactly as it would without Impex. Mail sent
through an Impex `mail` channel carries an `X-Impex-Channel` header and is not
recorded twice.

If the mailer cannot be switched, `impex.mail.record_unwrapped` records mail
from any other mailer through the `MessageSent` event instead. That sees only
mail that was sent — a failed send never fires the event — which is why
wrapping is the better choice.

## Managing stored channels

Over HTTP (see [HTTP API](09-api.md#channels)), MCP
(`create-channel-tool`, `update-channel-tool`, `delete-channel-tool`,
`rotate-channel-secret-tool`), or the dashboard's Channels screen to read them.

```json
POST impex/channels
{
  "name": "warehouse-api",
  "direction": "outbound",
  "transport": "http",
  "body_policy": "failures",
  "options": { "url": "https://wms.example.com/events" },
  "credentials": { "signing_secret": "whsec_…" }
}
```

- A name is lowercase letters, digits, `.`, `_` and `-`. It is on every ledger
  row, so it never changes; nor does the direction.
- Updating `options` or `credentials` replaces the whole value.
- Deleting a channel leaves its traffic in the ledger: rows carry the name, not
  a key.
- With `impex.authorization` on, `ChannelPolicy` lets anyone signed in read the
  application's channels (those with no owner) and create one; a stored
  channel's owner alone may read, change or delete it.
