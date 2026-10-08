# Subscriptions

Vendors, partners and other systems want to hear when your data changes — but
only the data they care about. Subscriptions let them follow a **stream** of
change, choose the **subjects** and **topics** they want, and have it pushed to
an endpoint in signed batches or pull it from a feed. Every push goes out
through an outbound [channel](18-channels.md), so every delivery is in the
[ledger](07-ledger.md).

Subscriptions need nothing but Impex. Any application or package offers its
own data by writing a stream — a class, usually extending `AbstractStream` —
and reporting change to it; Impex does the rest: the subscriber API, filters,
detection, signed batched delivery, retries, the feed, exports, MCP tools and
the dashboard. jayi/keystone's product stream is one such stream, not a
requirement.

## Concepts

| Term | Meaning |
|---|---|
| **Stream** | A source of change a package registers: products, manufacturers, orders. Identified by a permanent key such as `catalogue.products`. |
| **Subject** | One thing in a stream, by its subject key: a product's id, an order number. Use a key that never changes — a renamed key reads as one subject removed and another added, and breaks every subscriber's list. Carry changeable codes such as a SKU as data. |
| **Topic** | A part of a subject a subscriber can choose to hear about: `pricing`, `assets`, `content`. |
| **Subscriber** | Who receives changes: a vendor, a partner. Authenticates as an OAuth client. |
| **Subscription** | A subscriber's interest in one stream: which subjects, which topics, which format, delivered where. A subscriber following several streams — products and orders, say — holds one subscription per stream, each with its own endpoint (or the same URL twice) and its own cursor. |
| **Event** | One recorded change, in a sequence whose id is every subscriber's cursor. |
| **Delivery** | One attempt to push a batch of events to a subscription's endpoint. |

### Two kinds of stream

| Kind | How change is reported | What is sent |
|---|---|---|
| `snapshot` | The package **touches** subjects that may have changed. Impex loads them, hashes each topic's slice, and compares with last time. | Only topics whose hash moved. Ten saves that end where they started send nothing. |
| `append` | The package **publishes** discrete events: an order placed, a shipment dispatched. | Each event, as it is, in order. |

### Topics are bits

A stream's topics are stored as bit positions, so matching an event to a
subscription is one `AND`. That makes the order **permanent**: append new
topics at the end, and never reorder or remove one. A stream may declare at
most 31.

### Which subjects

A subscription's `selection` is one of:

| Selection | Covers |
|---|---|
| `all` | Every subject in the stream. |
| `filter` | Those its `filter` matches, by the stream's matcher. |
| `list` | An explicit list of subject keys, in `impex_subscription_subjects`. |

## Writing a stream

Extend `AbstractStream`. It supplies the defaults most streams want: snapshot
kind, slicing by top-level key, no filters, the three standard formats, and no
export.

```php
namespace App\Streams;

use App\Models\Product;
use JayI\Impex\Domains\Subscription\Contracts\SubscriptionMatcher;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Domains\Subscription\Support\AbstractStream;

final class ProductStream extends AbstractStream
{
    public function key(): string
    {
        return 'catalogue.products';   // stored on every event and subscription
    }

    public function topics(): array
    {
        return ['content', 'pricing', 'assets'];   // append only
    }

    /**
     * Keys are the products' ids: permanent, unlike a SKU, which a rename
     * would turn into one product removed and another added, breaking every
     * subscriber's list. One query for the whole chunk, never one per key. A
     * key with no subject maps to null: Impex sends it as removed.
     */
    public function snapshots(array $keys): array
    {
        $products = Product::query()
            ->with(['prices', 'media'])
            ->whereIn('id', $keys)
            ->get()
            ->keyBy('id');

        $snapshots = [];

        foreach ($keys as $key) {
            $product = $products->get($key);

            $snapshots[$key] = $product === null ? null : [
                // Outside the topics: never sent, but a change here can move
                // the product into or out of a filtered subscription.
                'category' => $product->category_slug,
                // The SKU travels as data, so a renamed SKU is a content
                // change, not a different product.
                'content' => ['sku' => $product->sku, 'name' => $product->name, 'description' => $product->description],
                'pricing' => $product->prices->map->only(['currency', 'amount'])->values()->all(),
                'assets' => $product->media->pluck('url')->all(),
            ];
        }

        return $snapshots;
    }

    /**
     * Keyed under `filter.`. The listed names become the stream's filters.
     */
    public function filterRules(): array
    {
        return [
            'filter.categories' => ['required', 'array'],
            'filter.categories.*' => ['string'],
        ];
    }

    public function matcher(): SubscriptionMatcher
    {
        return new CategoryMatcher;
    }

    public function export(SubscriptionModel $subscription, mixed $handle): void
    {
        $categories = $subscription->filter['categories'] ?? null;

        Product::query()
            ->when($categories !== null, fn ($query) => $query->whereIn('category_slug', $categories))
            ->select(['id'])
            ->chunkById(1000, function ($products) use ($handle): void {
                foreach ($this->snapshots($products->modelKeys()) as $id => $snapshot) {
                    fwrite($handle, json_encode(['subject' => $id, 'data' => $snapshot]).PHP_EOL);
                }
            });
    }
}
```

The matcher is called once per chunk of changes with every filtered
subscription to the stream. Build an index over their filters once, then test
each subject against it. **Never load a subscription's whole scope**: at ten
million subjects, a category subscription is millions of rows.

```php
use JayI\Impex\Domains\Subscription\Contracts\SubscriptionMatcher;

final class CategoryMatcher implements SubscriptionMatcher
{
    public function match(array $snapshots, array $subscriptions): array
    {
        $byCategory = [];

        foreach ($subscriptions as $subscription) {
            foreach ($subscription->filter['categories'] ?? [] as $category) {
                $byCategory[$category][] = $subscription->id;
            }
        }

        $matches = [];

        foreach ($snapshots as $key => $snapshot) {
            foreach ($byCategory[$snapshot['category'] ?? ''] ?? [] as $id) {
                $matches[$id][] = (string) $key;
            }
        }

        return $matches;   // subject keys, by subscription id
    }
}
```

### The contract

| Method | Notes |
|---|---|
| `key()` | Permanent. |
| `kind()` | `StreamKind::Snapshot` (default) or `StreamKind::Append`. |
| `topics()` | Append only, at most 31. |
| `snapshots(array $keys)` | Current state by key; `null` for one that no longer exists. Called with up to `detection.chunk` keys. |
| `slice(array $snapshot)` | The snapshot divided by topic. The default takes the top-level key of each topic's name. Keep slices deterministic — same state, same slice, same key order — because each is hashed. |
| `matcher()` | For `filter` subscriptions. The default matches every subject. |
| `filterRules()` | Validation for a subscription's `filter`, keyed under `filter.`. |
| `formats()` | Formats a subscription can ask for, by name, as `Formatter` instances. Default `thin`, `slice`, `full`. |
| `export($subscription, $handle)` | Every subject the subscription covers, one JSON object per line. The default throws: such a stream cannot export. |

A stream that supports `list` subscriptions should honour the list in
`export()` too; the shape of each exported line is the stream's choice.

### An append stream

Orders, shipments, payments: things that happen, rather than state to compare.
An append stream needs only a key, its topics and its kind. It loads nothing,
because each event carries its own payload.

```php
use JayI\Impex\Domains\Subscription\Enums\StreamKind;
use JayI\Impex\Domains\Subscription\Support\AbstractStream;

final class OrderStream extends AbstractStream
{
    public function key(): string
    {
        return 'orders';
    }

    public function kind(): StreamKind
    {
        return StreamKind::Append;
    }

    public function topics(): array
    {
        return ['status', 'shipping', 'payments'];   // append only
    }
}
```

Every event published to it reaches each subscription following one of its
topics, in order and never folded together: two status changes in a minute are
two entries. With no filter rules or matcher of its own, a filtered
subscription to an append stream matches every subject; list subjects
(`"subjects": ["SO-1001"]`) to follow particular orders.

### Example: a customer following a million products, only prices and stock

A customer's purchasing system wants price and stock changes for the million
products it carries, and nothing else: no description edits, no image swaps.
Nothing here needs a domain package; it builds on the `ProductStream` above,
with an `inventory` topic appended (topics are only ever appended):

```php
public function topics(): array
{
    return ['content', 'pricing', 'assets', 'inventory'];
}

// …and in snapshots(), beside the other topics:
'inventory' => ['on_hand' => $product->stock_on_hand, 'lead_days' => $product->lead_days],
```

**Say which million — by rule if you can.** A rule costs nothing per product:
a subscription to two brands or forty categories is a few bytes, however many
products those cover, and products that join the brand later are covered
automatically. Give the stream a filter for it (a `brands` filter beside
`categories`, matched the same way as `CategoryMatcher`), and the customer
subscribes with it:

```http
POST /impex/subscriber/subscriptions
{
  "stream": "catalogue.products",
  "topics": ["pricing", "inventory"],
  "filter": {"brands": ["schlage", "von-duprin"]},
  "format": "slice",
  "endpoint": {"url": "https://purchasing.customer.example/hooks/products"}
}
```

If the million is an arbitrary list of products, list them instead — by the
stream's subject key, the product ids, never SKUs, so a renamed SKU leaves the
list intact. Create the subscription with the first 10,000 in `subjects`, then
add the rest 10,000 at a time — a hundred calls for a million:

```http
POST /impex/subscriber/subscriptions/{id}/subjects
{"add": ["01J9XQ4C2V…", "01J9XQ4C2W…", "…"]}
```

The customer learns the ids from the export below, which carries each
product's id and current SKU. A list is one row per product, looked up only for
the products in each chunk of changes, so it is cheap to match against — but
it is a million rows to keep in step with what the customer carries. Prefer a
rule when one exists.

**Start from a file, not a million webhooks.** Ask for an export once the
subscription exists. It writes every product the subscription covers to one
JSONL file and moves the subscription's cursor past it, so pushes pick up from
there:

```http
POST /impex/subscriber/subscriptions/{id}/export
```

**Give every entry the current SKU.** The customer follows `pricing` and
`inventory`, so it would never see the SKU, which lives in `content`. A
formatter can add it to every entry, read from the product as it is when the
batch is sent:

```php
use JayI\Impex\Domains\Subscription\Contracts\Stream;
use JayI\Impex\Domains\Subscription\Formatters\SliceFormatter;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

final class ProductSliceFormatter extends SliceFormatter
{
    public function format(Stream $stream, SubscriptionModel $subscription, array $events, array $snapshots): array
    {
        return array_map(
            fn (array $entry): array => [...$entry, 'sku' => $snapshots[$entry['subject']]['content']['sku'] ?? null],
            parent::format($stream, $subscription, $events, $snapshots),
        );
    }
}

// In ProductStream:
public function formats(): array
{
    return [...parent::formats(), 'slice' => new ProductSliceFormatter];
}
```

A removed product has no snapshot, so its entry carries no SKU; the customer
already knows it by id.

**What arrives afterwards.** Only changes in `pricing` or `inventory`, and only
those topics' data:

- A description is rewritten: `content` changed, which the customer does not
  follow. Nothing is sent.
- A price changes: one entry with `topics: ["pricing"]` and only the prices.
- A price and the stock level change before the next delivery: the two are
  folded into one entry carrying both topics, because the customer wants the
  product as it is now, not every step on the way.
- A nightly price file rewrites all million prices but 30,000 actually move:
  30,000 events, delivered as 300 requests of 100.

```json
{
  "type": "events",
  "subscription": "01J9Z3…",
  "stream": "catalogue.products",
  "events": [
    {
      "id": "48211907",
      "type": "changed",
      "subject": "01J9XQ4C2VR7M8D2N6Q0F3K5TB",
      "sku": "SCH-ND80PD-RHO-626",
      "topics": ["pricing", "inventory"],
      "occurred_at": "2026-10-08T14:02:11+00:00",
      "data": {
        "pricing": [{"currency": "USD", "amount": "182.40"}],
        "inventory": {"on_hand": 12, "lead_days": 3}
      }
    }
  ],
  "cursor": "48211907"
}
```

**What it costs.** The customer's subscription adds nothing to the write path
and nothing per product. Each real change to a product the customer follows,
in a topic it follows, adds one narrow routing row. The payload is built when
the batch is sent, from the product as it is then. If the customer's endpoint
is down, events wait under the subscription's cursor and are delivered in
order once it recovers — or the customer reads them from the
[feed](#the-feed).

### Example: each customer gets only their own orders

Customers subscribe to the orders stream and should receive their own orders
and nobody else's. Who a subscriber is comes from the operator side, never
from anything the subscriber sends: set the subscriber's **owner** to the
customer when you register it, and have the stream's matcher compare each
order's customer with the subscriber's owner.

> **Guard the stream yourself.** Impex runs a stream's matcher for
> subscriptions created with a `filter` only: one with no filter takes every
> subject, and one listing `subjects` takes those, whoever owns them. That is
> right for a public catalogue and wrong for anyone's orders. Which streams
> are private to their subscriber is your application's knowledge, so the
> guard is yours: refuse any subscription to such a stream that is not
> filtered, or that lists subjects. [Guarding it](#guarding-a-per-account-stream)
> below shows how.

Register each customer as a subscriber, owned by your customer record and
authenticating as the customer's OAuth client:

```php
use JayI\Impex\Domains\Subscription\Actions\CreateSubscriberAction;

app(CreateSubscriberAction::class)->execute(
    ['name' => $customer->name, 'client_id' => $customer->oauth_client_id],
    owner: $customer,
);
```

The stream is an append stream whose payloads carry the customer:

```php
use JayI\Impex\Domains\Subscription\Contracts\SubscriptionMatcher;
use JayI\Impex\Domains\Subscription\Enums\StreamKind;
use JayI\Impex\Domains\Subscription\Support\AbstractStream;

final class CustomerOrderStream extends AbstractStream
{
    public function key(): string
    {
        return 'orders';
    }

    public function kind(): StreamKind
    {
        return StreamKind::Append;
    }

    public function topics(): array
    {
        return ['status', 'shipping', 'invoices'];
    }

    public function filterRules(): array
    {
        // The filter carries no choice of customer — that comes from the
        // subscriber — only that the subscription is scoped.
        return ['filter.own' => ['required', 'accepted']];
    }

    public function matcher(): SubscriptionMatcher
    {
        return new CustomerOrderMatcher;
    }
}
```

```php
Impex::streams()->publish('orders', $order->number, 'order.shipped', [
    'customer_id' => $order->customer_id,
    'status' => $order->status,
    'shipping' => ['carrier' => 'UPS', 'tracking' => $tracking],
], topics: ['shipping']);
```

The matcher looks up every subscriber's owner in one query, indexes the
subscriptions by customer, and gives each order to its customer's
subscriptions only. Each order is one lookup, however many customers
subscribe:

```php
use JayI\Impex\Domains\Subscription\Contracts\SubscriptionMatcher;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

final class CustomerOrderMatcher implements SubscriptionMatcher
{
    public function match(array $snapshots, array $subscriptions): array
    {
        $owners = SubscriberModel::query()
            ->whereIn('id', array_map(fn ($subscription) => $subscription->subscriber_id, $subscriptions))
            ->where('owner_type', (new Customer)->getMorphClass())
            ->pluck('owner_id', 'id');

        $byCustomer = [];

        foreach ($subscriptions as $subscription) {
            $customer = $owners[$subscription->subscriber_id] ?? null;

            if ($customer !== null) {
                $byCustomer[(string) $customer][] = $subscription->id;
            }
        }

        $matches = [];

        foreach ($snapshots as $order => $payload) {
            foreach ($byCustomer[(string) ($payload['customer_id'] ?? '')] ?? [] as $id) {
                $matches[$id][] = (string) $order;
            }
        }

        return $matches;
    }
}
```

A customer's subscription is then created with `"filter": {"own": true}`:

```http
POST /impex/subscriber/subscriptions
{"stream": "orders", "topics": ["status", "shipping"], "filter": {"own": true},
 "endpoint": {"url": "https://erp.customer.example/hooks/orders"}}
```

A subscriber with no owner, or owned by something other than a customer,
matches nothing.

#### Guarding a per-account stream

Every way a subscription is made or changed — the subscriber API, the operator
API, MCP — goes through an action that fires a `…ingActionEvent` first. A
listener that throws stops the action before anything is saved, and a
`ValidationException` answers the caller `422`. Three listeners keep a
per-account stream scoped:

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use JayI\Impex\Domains\Subscription\Events\SubscriptionCreatingActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscriptionSubjectsUpdatingActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscriptionUpdatingActionEvent;

// In a service provider's boot().
$private = ['orders', 'invoices'];

// Created: must be filtered, and may not list subjects.
Event::listen(function (SubscriptionCreatingActionEvent $event) use ($private): void {
    if (in_array($event->data['stream'] ?? null, $private, true)
        && (empty($event->data['filter']) || ! empty($event->data['subjects']))) {
        throw ValidationException::withMessages([
            'filter' => 'This stream sends only your own records: subscribe with "filter": {"own": true}, without subjects.',
        ]);
    }
});

// Changed: the filter may not be dropped.
Event::listen(function (SubscriptionUpdatingActionEvent $event) use ($private): void {
    if (in_array($event->subscription->stream, $private, true)
        && array_key_exists('filter', $event->data) && empty($event->data['filter'])) {
        throw ValidationException::withMessages(['filter' => 'This stream\'s subscriptions must stay filtered.']);
    }
});

// Listed: no subjects may be added.
Event::listen(function (SubscriptionSubjectsUpdatingActionEvent $event) use ($private): void {
    if (in_array($event->subscription->stream, $private, true) && ! empty($event->data['add'])) {
        throw ValidationException::withMessages(['add' => 'This stream does not take a list of subjects.']);
    }
});
```

With those in place the matcher decides every delivery, and the matcher reads
the customer from the subscriber's owner, which only you set. Test the guard
like any other rule: a subscription with no filter, with subjects, or with its
filter removed must be refused.
 The same pattern serves any per-account stream: invoices,
quotes, shipments, returns.

### Registering it

In config:

```php
// config/impex.php
'streams' => [
    \App\Streams\ProductStream::class,
],
```

Or from a package's service provider — see
[Extending](12-extending.md#registering-streams-from-a-package):

```php
Impex::streams()->register(ProductStream::class);
```

### Reporting change

```php
// A snapshot stream: after a save, an import, a price feed.
Impex::streams()->touch('catalogue.products', [$product->id]);
Impex::streams()->touch('catalogue.products', $ids);   // any iterable of keys

// An append stream: one event, delivered as it is.
Impex::streams()->publish('orders', $order->number, 'order.shipped', ['carrier' => 'UPS']);
Impex::streams()->publish('orders', $order->number, 'order.refunded', $payload, topics: ['payments']);
```

Both are cheap enough for a request: one write, nothing per subscriber, no
HTTP. The work happens on the queue.

**Touch freely.** A subject touched a thousand times before it is looked at is
looked at once, and one that ends up as it was sends nothing. Inside a
database transaction the touch commits or rolls back with it, and the
detection job is dispatched after commit. Touching an append stream, or
publishing to a snapshot stream, throws.

The first time a subject is detected it has no previous hashes, so it counts
as changed in every topic it has something in. Touching a whole catalogue for
the first time therefore sends all of it to every subscriber that covers it.

## What a subscriber receives

### Formats

| Format | Each entry carries |
|---|---|
| `thin` | Which subject changed, in which topics. For subscribers who fetch the data themselves. |
| `slice` (default) | Plus `data`: only the topics that changed **and** the subscriber asked for. A price change reaches a pricing subscriber as prices, not as the whole product. |
| `full` | Plus `data`: the whole snapshot, whatever changed. |

The default for new subscriptions is `impex.subscriptions.default_format`.
Formatting runs at **send time**, against subjects as they are then, so what a
subscriber gets is never older than the moment it was sent — and payloads are
never copied per subscriber.

### An entry

```json
{
  "id": "1041",
  "type": "changed",
  "subject": "DRL-100",
  "topics": ["pricing"],
  "occurred_at": "2026-10-07T09:14:02+00:00",
  "data": { "pricing": [{ "currency": "USD", "amount": 129 }] }
}
```

| Field | Meaning |
|---|---|
| `id` | The event id, as a string. |
| `type` | `changed`, `removed` or `appended`. |
| `name` | Present when set: an append event's name (`order.shipped`), or `entered_scope` / `left_scope`. |
| `subject` | The subject key. |
| `topics` | The event's topics that the subscription follows. |
| `occurred_at` | ISO 8601. |
| `data` | `slice` and `full` only. An append event's payload; nothing for a removal. |

Events for a snapshot stream are **collapsed** before they are sent: ten price
changes to one subject in a minute become one entry carrying every topic any of
them touched. A removal resets what came before it. Append events are never
collapsed; each one happened.

### The delivery envelope

Pushed to the endpoint as a `POST` with a JSON body:

```http
POST https://vendor.example.com/hooks
Content-Type: application/json
webhook-id: 01JR8K….1041-1187
webhook-timestamp: 1791364442
webhook-signature: v1,K5oZfzN95Z9UVu1EsfQmfVNQhnkZ2pj9o9NDN/H/pI4=
```

```json
{
  "type": "events",
  "subscription": "01JR8K…",
  "stream": "catalogue.products",
  "events": [
    { "id": "1041", "type": "changed", "subject": "DRL-100", "topics": ["pricing"], "occurred_at": "…", "data": { "pricing": [] } },
    { "id": "1102", "type": "changed", "name": "entered_scope", "subject": "SAW-220", "topics": ["pricing", "assets"], "occurred_at": "…", "data": { "pricing": [], "assets": [] } },
    { "id": "1187", "type": "removed", "name": "left_scope", "subject": "DRL-090", "topics": ["pricing", "assets"], "occurred_at": "…" }
  ],
  "cursor": "1187"
}
```

- `cursor` is the last event the batch covers. The subscription's cursor moves
  there only once the endpoint answers **2xx**.
- `webhook-id` is `{subscription}.{first}-{last}`: the same range keeps the same
  id across retries, so a receiver that already processed it can tell a
  redelivery from new work.
- A `ping` has `"type": "ping"`, an empty `events` list and no `cursor`, under a
  `ping.{ulid}` id.
- With `impex.subscriptions.delivery.gzip` on, new endpoints are created with
  `gzip`, and bodies arrive with `Content-Encoding: gzip`.
- An endpoint with the `mail` transport receives a digest mail instead —
  "N change(s) in {stream}", one line per entry — for a person rather than a
  system.

### Verifying a delivery

Deliveries are signed per [Standard Webhooks](https://www.standardwebhooks.com)
with the secret returned when the subscription was created. A receiver
verifies the base64 HMAC-SHA256 of `{webhook-id}.{webhook-timestamp}.{body}`
under the secret, base64-decoded after its `whsec_` prefix:

```php
$key = base64_decode(substr($secret, strlen('whsec_')));
$signed = $request->header('webhook-id').'.'.$request->header('webhook-timestamp').'.'.$request->getContent();
$expected = 'v1,'.base64_encode(hash_hmac('sha256', $signed, $key, true));

$valid = collect(explode(' ', (string) $request->header('webhook-signature')))
    ->contains(fn (string $signature): bool => hash_equals($expected, $signature));
```

During a secret rotation the header carries one signature per secret, so
check each. A receiver that is itself an Impex application can point an inbound
channel at `StandardWebhooksValidator` instead.

## The subscriber API

Subscribers manage their own subscriptions under `{prefix}/subscriber`
(`impex/subscriber` by default), behind `impex.routes.subscriber_middleware`.
Put the application's OAuth middleware there:

```php
// config/impex.php
'routes' => [
    'subscriber_middleware' => ['api', 'auth:api'],   // or ['api', 'client:impex'] for client-credentials
],
```

Authentication is the application's. Impex then finds **which subscriber** the
request is, with the resolver in `impex.subscriptions.resolver`. The default,
`OAuthClientResolver`, matches an active subscriber's `client_id` against, in
order: the request attribute `oauth_client_id`, the authenticated user's
`token()->client_id`, and the `aud` claim of the bearer token (read without
verifying, which is safe only because the auth middleware has already verified
it). A request it cannot place answers `403`:

```json
{ "message": "No active subscriber is registered for these credentials." }
```

A subscriber sees and touches only its own subscriptions; another's answers
`403`.

| Method | Path | Does |
|---|---|---|
| `GET` | `subscriber/streams` | What can be followed: each stream's `key`, `kind`, `topics`, `formats` and `filters`. |
| `POST` | `subscriber/subscriptions` | Subscribe. `201`, with the signing secret. |
| `GET` | `subscriber/subscriptions` | Your subscriptions. Filters `stream`, `status`; cursor paginated. |
| `GET` | `subscriber/subscriptions/{subscription}` | One. |
| `PATCH` | `subscriber/subscriptions/{subscription}` | Change topics, format, filter, options, endpoint; pause, resume, replay. |
| `DELETE` | `subscriber/subscriptions/{subscription}` | `204`. |
| `POST` | `subscriber/subscriptions/{subscription}/ping` | Send a signed, empty ping and report how it went. |
| `POST` | `subscriber/subscriptions/{subscription}/export` | Queue a full export. `202`. |
| `POST` | `subscriber/subscriptions/{subscription}/rotate-secret` | A new signing secret. |
| `POST` | `subscriber/subscriptions/{subscription}/subjects` | Add or remove explicit subject keys. |
| `GET` | `subscriber/subscriptions/{subscription}/events` | The feed. |
| `GET` | `subscriber/subscriptions/{subscription}/deliveries` | Delivery attempts, newest first. |

### Subscribing

```http
POST /impex/subscriber/subscriptions
Authorization: Bearer …
Content-Type: application/json

{
  "stream": "catalogue.products",
  "topics": ["pricing", "assets"],
  "filter": { "categories": ["power-tools"] },
  "format": "slice",
  "endpoint": { "url": "https://vendor.example.com/hooks" }
}
```

| Field | Notes |
|---|---|
| `stream` | Required. |
| `topics` | Topic names. Omit or `[]` for all of them. An unknown topic is refused with the list to choose from. |
| `format` | A format the stream offers. Defaults to `default_format`. |
| `filter` | Validated by the stream's `filterRules()`. Sets `selection` to `filter`. |
| `subjects` | Up to 10,000 subject keys. Sets `selection` to `list`. |
| `options` | Free-form; stored on the subscription. |
| `endpoint` | Where to push: `url` (http), or `transport: "mail"` with `to`. **Omit it for a feed-only subscription.** |

`201`, abridged:

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
    "status": "active",
    "endpoint": {
      "channel": "subscription-01JR8K…",
      "transport": "http",
      "url": "https://vendor.example.com/hooks"
    },
    "last_delivered_at": null,
    "last_error": null,
    "created_at": "2026-10-07T09:14:02+00:00"
  },
  "secret": "whsec_…"
}
```

**The secret is in this response and no other.** The endpoint becomes an
outbound channel named `subscription-{id}`, owned by the subscriber — so the
[endpoint guard](18-channels.md#the-endpoint-guard) applies: the URL must be
https and publicly reachable, and is refused (`422`) here if not. The channel
keeps bodies by `impex.subscriptions.body_policy` (`failures` by default),
because a busy subscription sends far too much to keep it all.

A subscription's resource also carries `cursor`, `failures`, `pending`,
`paused_until`, `disabled_at`, `last_export_path`, `last_export_at` and
`options`. Never its secret.

### Changing a subscription

```json
PATCH /impex/subscriber/subscriptions/01JR8K…
{ "topics": ["pricing"], "endpoint": { "url": "https://vendor.example.com/v2/hooks" } }
```

Only the keys you send change. The stream is fixed; a different stream is a
different subscription.

| Field | Effect |
|---|---|
| `status: "paused"` | Events keep collecting; nothing is delivered. |
| `status: "active"` | Resumes a paused or disabled subscription: clears the failure count and backoff and delivers what is pending. |
| `replay_from` | Deliver again from this event id on. |
| `endpoint.url` / `endpoint.to` | Moves the endpoint (re-checked by the guard). |
| `filter: null` | Back to `all`. |

### Pinging

```http
POST /impex/subscriber/subscriptions/01JR8K…/ping
```

```json
{ "data": { "successful": true, "status_code": 200, "duration_ms": 84, "error": null, "message_id": "01JR8M…" } }
```

A feed-only subscription has nothing to ping and answers `409`.

### Rotating the secret

`POST …/rotate-secret` answers the subscription with a new `secret` beside it.
Deliveries then carry a signature under both the new and the previous secret
until the next rotation, so the receiver can switch at its own pace.

### Explicit subjects

```json
POST /impex/subscriber/subscriptions/01JR8K…/subjects
{ "add": ["DRL-100", "SAW-220"], "remove": ["DRL-090"] }
```

```json
{ "data": { "added": 2, "removed": 1 } }
```

Up to 10,000 of each per call. Adding keys switches the subscription to
`list`: it then follows only that list.

## The feed

Every subscription has a feed, pushed or not. Without an endpoint it is the
only way to read events.

```http
GET /impex/subscriber/subscriptions/01JR8K…/events?after=1187&limit=100
```

```json
{
  "data": [
    { "id": "1203", "type": "changed", "subject": "DRL-100", "topics": ["pricing"], "occurred_at": "…", "data": { "pricing": [] } }
  ],
  "cursor": "1203",
  "has_more": false
}
```

Entries are in the same shape a push delivers, collapsed the same way. Pass the
returned `cursor` back as `after` to read on; start from `after=0`. `limit`
defaults to 100 and is capped by `impex.subscriptions.feed.max_limit`.
**Reading never moves the subscription's own cursor**, so a subscriber can pull
and be pushed to without either stepping on the other.

Events are kept for `impex.retention.events_days` (30) whether or not they were
delivered. A subscriber can replay or catch up within that window and no
further.

## Exports

A new subscriber to a ten-million-product catalogue should not start from ten
million webhooks.

```http
POST /impex/subscriber/subscriptions/01JR8K…/export
```

`202`. An `ExportSubscription` job calls the stream's `export()`, writes the
result to `{exports.path}/{subscription}/{ulid}.jsonl` on `exports.disk`, sets
the subscription's `last_export_path` and `last_export_at`, and moves its
cursor to where the stream stood when the export began. Events up to there are
in the file; the ones after follow as usual. A subject changed during the
export may arrive twice — once in the file, once as an event — which a
subscriber taking state rather than diffs never notices.

Impex does not serve the file. Hand it over however your disk allows — a
temporary URL on S3, say — once `last_export_at` is set.

## Removals and scope

A subscriber's copy should not keep a subject it should no longer have.

- **A deleted subject** — `snapshots()` returns `null` for a key that had
  state — becomes a `removed` event, sent to the filtered and listed
  subscriptions that were sent that subject before, and to every subscription
  that takes everything.
- **A subject that leaves a subscription's scope** — moved out of a filtered
  category, say — is sent to that subscription as `removed`, named
  `left_scope`.
- **A subject that enters a scope** is sent **whole**, as `changed`, named
  `entered_scope`, in every topic the subscription follows — it never had the
  subject — unless the subject has nothing in those topics.

Scope can change without any topic changing: the product's `category` above is
outside every topic. Impex keeps a hash of the whole snapshot beside the
per-topic ones, so a subject whose topics are unchanged but whose snapshot
moved is still checked against every subscription's scope.

Who "was sent the subject before" is read from the event history, which covers
the retention window. A subscriber last sent a subject before then, or whose
latest word on it was already `removed`, is not told again. Only filtered and
listed subscriptions can gain or lose a subject, so only their history is read:
a subscription that takes everything never enters or leaves scope, and at
catalogue scale reading its history for every change would be most of the
work.

## Measuring it

The workbench carries a benchmark of the whole pipeline on synthetic data —
touching, detection with fan-out, a re-check where most subjects did not change,
and delivery to an endpoint that answers at once but is still recorded in the
ledger:

```bash
vendor/bin/testbench workbench:build
vendor/bin/testbench impex:bench --subjects=200000 --subscriptions=100 --changed=0.1
```

One process on SQLite, 200,000 subjects and 100 subscriptions (half taking
everything, the rest filtered or listed):

| Stage | Time | Throughput | Peak memory |
|---|---|---|---|
| Touch | 0.7s | ~300,000 subjects/s | 53 MB |
| Detect, first look (10.2M routed rows) | 50s | ~4,000 subjects/s | 83 MB |
| Re-detect, 10% changed | 8.4s | ~24,000 subjects/s | 87 MB |
| Deliver (111,780 batched requests) | 255s | ~44,000 events/s | 87 MB |

Time grows linearly with volume and memory stays flat. The first look is
dominated by writing a row per match; a new subscriber starts from an export
instead. Run it against the database you deploy on, and raise
`subscriptions.detection.concurrency` and the delivery workers to scale out —
SQLite numbers are a floor.

## Retries and the circuit breaker

- **One request in flight per subscription**, in order: a subscriber never
  receives batch two before batch one. A cache lock on `impex.cache.store`
  serialises deliveries.
- **Batched**: up to `delivery.batch` (100) events per request, collapsed.
- **The cursor moves on 2xx only.** Anything else, or no answer, is a failure.
- **A failure backs off**: `backoff.base` (10) seconds, doubling per
  consecutive failure up to `backoff.max` (3600), with ±20% jitter so a
  thousand subscriptions failing against the same outage do not all retry in
  the same second. `impex:tick` picks the subscription up again once its
  backoff has elapsed.
- **The breaker**: `breaker_threshold` (20) failures in a row switch the
  subscription to `disabled` and fire `SubscriptionDisabled`. Events keep
  collecting. Resume it with `status: "active"`; delivery continues from the
  cursor.
- Every attempt is a row in `impex_deliveries` (`GET …/deliveries`), linked to
  the ledger row of the request it sent.

```php
use JayI\Impex\Domains\Subscription\Events\SubscriptionDisabled;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;

Event::listen(function (SubscriptionDisabled $event): void {
    $subscription = SubscriptionModel::query()->with('subscriber')->find($event->subscriptionId);

    // Tell the subscriber their endpoint is down.
});
```

## Built for scale

Nothing here grows with catalogue size times subscriber count.

- **The write path is per subject, never per subscriber.** A touch is one
  upsert into `impex_stream_touches`; a burst of saves to one subject is one
  row.
- **Detection works in chunks.** A `DetectStream` job claims up to
  `detection.chunk` (1000) touched subjects under a lease, loads them with one
  `snapshots()` call, compares one xxh3 hash per topic, and writes the events,
  the new hashes and the fan-out in **one transaction** — so a worker killed
  halfway leaves the chunk to be claimed again rather than half-sent. A
  subject touched again while it was being looked at keeps its touch for the
  next pass. `detection.concurrency` jobs share a backlog through the leases;
  each runs for up to `detection.time_budget` seconds, then queues itself
  again.
- **Filters are matched as rules, not expanded.** A subscription to a
  category of two million products costs nothing until one of them changes.
- **Fan-out writes one narrow row per match** — `(subscription_id, event_id)`
  in `impex_subscription_events` — and nothing else. A change is one event
  row however many subscribers hear of it.
- **Delivery reads one range** of a subscription's own rows forward from its
  cursor, however long the stream.
- **Payloads are built at send time**, from current state, never copied per
  subscriber.
- **Detection and delivery queue separately** (`subscriptions.queue.detect`,
  `subscriptions.queue.deliver`), so a big import cannot hold up deliveries
  behind it. At most one `DetectStream` waits per stream and shard, and one
  `DeliverSubscription` per subscription.
- **Pruning deletes by id range**, ten thousand events a slice, rather than one
  long delete on a table with hundreds of millions of rows.
- **`impex:tick` is the safety net**: it queues detection a lost job never ran,
  and deliveries whose backoff has elapsed.

## Managing subscriptions

Operators manage subscribers and their subscriptions on their behalf.

**Over HTTP**, on the operator stack (`impex.routes.middleware`):

```
GET    impex/streams
GET    impex/subscribers                         POST   impex/subscribers
GET    impex/subscribers/{subscriber}            PATCH  impex/subscribers/{subscriber}
DELETE impex/subscribers/{subscriber}
POST   impex/subscribers/{subscriber}/subscriptions
GET    impex/subscriptions                       (filters: stream, status, subscriber)
```

plus every `subscriptions/{subscription}/…` route of the subscriber API, under
`impex/`. See [HTTP API](09-api.md#subscriptions).

```json
POST impex/subscribers
{ "name": "Acme Tools", "client_id": "9c1e…", "metadata": { "account": "ACME-01" } }
```

`client_id` is the OAuth client the subscriber's systems authenticate as on
the subscriber API. Deleting a subscriber deletes each of its subscriptions,
their pending events and their endpoint channels; the ledger keeps what was
sent.

With `impex.authorization` on, a subscriber belongs to the user who created it
(`SubscriberPolicy`), a subscription follows its subscriber
(`SubscriptionPolicy`), and listings show only the user's own.

**Over MCP**: `list-streams-tool`, the subscriber tools and the subscription
tools — see [MCP](10-mcp.md#tools).

**On the dashboard**: the Subscriptions screen lists them by stream and status,
and a subscription's page shows its topics, endpoint, cursor, backoff and last
error, its recent deliveries linked to the ledger, and pause, resume, replay
and ping. See [Dashboard](11-dashboard.md#subscriptions).
