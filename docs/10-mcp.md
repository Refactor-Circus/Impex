# MCP

The same operations over MCP as over HTTP. Both surfaces call one Action, so
they cannot drift.

```php
// config/impex.php — both transports ship disabled
'mcp' => [
    'web' => [
        'enabled' => true,
        'route' => 'mcp/impex',
        'middleware' => ['auth:api'],
    ],
    'local' => [
        'enabled' => true,
        'handle' => 'impex',
    ],
],
```

**Add auth middleware to the web transport before enabling it.** The server
triggers and cancels workflows and reads every recorded payload.

With `impex.authorization` on, each tool acts as the authenticated user and
checks the same policy ability as its HTTP endpoint; a denied call answers
`Unauthorized.`. See [Ownership](08-ownership.md#authorizing-the-api).

## Tools

The server lists two entry points, `search_tools` and `execute_tools`, and keeps
the Impex tools behind them, so a client loads only the schemas it searches for.
Search the catalog with a term (an empty query browses it), then call a tool by
name through `execute_tools`:

```json
{ "calls": [{ "name": "list-flows-tool", "arguments": {} }] }
```

| Tool | Action |
|---|---|
| `list-flows-tool` | Registered flows, with enabled state and schedule |
| `run-flow-tool` | Start a run. Asynchronous. |
| `list-runs-tool` | Filter by status, flow, trigger, owner, tag, dates. Cursor paginated. |
| `show-run-tool` | One run with owners and step history |
| `cancel-run-tool` | Cancel an unfinished run |
| `retry-run-tool` | Re-queue a drive |
| `list-run-steps-tool` | Recorded steps, optionally by phase |
| `signal-run-tool` | Deliver a signal to a waiting run |
| `list-run-owners-tool` | Models with a stake in a run |
| `attach-run-owner-tool` | Give a model a stake |
| `detach-run-owner-tool` | Remove one |
| `list-messages-tool` | The data-flow ledger |
| `show-message-tool` | One message with headers and body preview |
| `list-channels-tool` | Every channel, inbound and outbound, configured and stored. Never returns secrets. |
| `show-channel-tool` | One channel by name |
| `create-channel-tool` | Create a stored channel |
| `update-channel-tool` | Change a stored channel's transport, status, body policy, options or credentials |
| `delete-channel-tool` | Delete a stored channel. Its traffic stays in the ledger. |
| `rotate-channel-secret-tool` | A new signing secret, the previous one still valid. Returned once. |
| `list-streams-tool` | Streams subscribers can follow, with topics, formats and filters |
| `list-subscribers-tool` | Subscribers |
| `show-subscriber-tool` | One subscriber and how many subscriptions it has |
| `create-subscriber-tool` | Register a subscriber, with its OAuth `client_id` |
| `update-subscriber-tool` | Change a subscriber's name, client, status or metadata |
| `delete-subscriber-tool` | Delete a subscriber and every subscription it has |
| `list-subscriptions-tool` | Filter by stream, status or subscriber |
| `show-subscription-tool` | One subscription: topics, filter, endpoint, cursor, delivery health. Never its secret. |
| `create-subscription-tool` | Subscribe a subscriber to a stream. The signing secret is returned once. |
| `update-subscription-tool` | Change, pause, resume (`status: active`) or replay (`replay_from`) a subscription |
| `delete-subscription-tool` | Delete a subscription and its pending events |
| `ping-subscription-tool` | Send a signed, empty ping to the endpoint and report how it answered |
| `export-subscription-tool` | Queue a full export of everything the subscription covers |
| `update-subscription-subjects-tool` | Add or remove explicit subject keys |
| `list-subscription-events-tool` | Read the feed after a cursor |
| `list-deliveries-tool` | Delivery attempts, newest first |
| `list-impex-history-tool` | Impex's audit history, newest first. Needs an audit log ([refactor-circus/keen](https://github.com/Refactor-Circus/Keen)) installed. |

Each name is the tool's class name in kebab case (`ListChannelsTool` is
`list-channels-tool`), as laravel/mcp derives it. Channel tools name a stored
channel by `channel`; subscription tools take `subscription` or `subscriber`
ids. A subscription's endpoint secret is rotated with
`rotate-channel-secret-tool` on its channel, `subscription-{id}`.

The catalog is `ImpexServer::TOOLS`. The same list is registered with
[Cortex](../README.md#cortex) when it is installed.

## Server instructions

The server tells an agent the things it cannot infer from a schema:

> Track and control Impex workflows, and inspect the flow of data in and out
> of this application. A run is one execution of a flow; its steps are the
> recorded history of what it did, in replay order. Starting a run is always
> asynchronous — run-flow-tool returns a pending run and the work is queued, so poll
> show-run-tool rather than expecting a result. A run with status "waiting" is
> blocked on a signal or a timer: signal-run-tool releases it. A failed run may have
> rolled back: list-run-steps-tool with phase "rollback" shows what was rolled back.
> The ledger (list-messages-tool) records every payload that has crossed the
> application boundary in either direction, linked to the run and step that
> caused it. Payloads are never inlined in listings — large results live on an
> artifact disk and are referenced by id. Every channel (list-channels-tool) is
> a named way in or out — http, mail or file — and every crossing is in the
> ledger. Subscribers follow streams (list-streams-tool) through subscriptions:
> changes are pushed to a subscription's endpoint in batches, or pulled from its
> feed (list-subscription-events-tool); list-deliveries-tool shows how each
> push went.

When Cortex is installed and publishes an instructions override for the
`impex` server, that override is served instead.

## Parity

An arch test compares each domain's `Actions/` against its `Mcp/Requests/` and fails when
an Action has no tool:

```php
// tests/ArchTest.php
arch('every use case is reachable from both the HTTP API and MCP')
    ->expect(fn (): array => parityGaps())
    ->toBeEmpty();
```

Deliberate omissions go in a `MCP_EXCEPTIONS` map with a reason, so an omission
is a test failure but an exception is one line. Cortex, for comparison, keeps
its tool-description endpoints HTTP-only; Impex currently has no exceptions.
The history tool comes from refactor-circus/keystone rather than an Impex Action, so it
sits outside the comparison; `GET impex/history` is its HTTP twin.

## Errors

An `ImpexException` (a `RefactorCircus\Keystone\Exceptions\PackageException`) surfaces its message rather than a generic failure, because
those messages carry guidance an agent can act on:

```
The flow [extract-products] is disabled. Re-enable it by removing or updating
its row in impex_flows.
```

A missing record returns `Not found.` rather than throwing.

## Testing tools

The catalog tools sit behind `execute_tools`, so a test calls them through it —
calling `RunFlowTool` directly answers `Tool [run-flow-tool] not found.`:

```php
use RefactorCircus\Impex\Mcp\ImpexServer;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;

$execute = (new ImpexServer(new FakeTransporter))->createContext()->tools()
    ->firstOrFail(fn (Tool $tool): bool => $tool->name() === 'execute_tools');

ImpexServer::tool($execute, ['calls' => [
    ['name' => 'run-flow-tool', 'arguments' => ['flow' => 'linear', 'arguments' => [1]]],
]])
    ->assertOk()
    ->assertSee('completed');
```
