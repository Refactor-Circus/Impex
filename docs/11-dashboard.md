# Dashboard

Impex renders its dashboard through [Atrium](https://github.com/Refactor-Circus/Atrium).
There is no separate bundle, no build step, and no auth mode to choose: the
pages are server-rendered Blade behind Atrium's gate.

## Setup

Atrium is suggested, not required: Impex lists `refactor-circus/atrium` in
`suggest`, and without it Impex serves its HTTP API and MCP server with no
dashboard and no `atrium.` routes. Install it to get the screens, at least the
screen-extensions registry release (0.1.0); a `conflict` on
`refactor-circus/atrium` `<0.1.0` refuses an older one at `composer update`.
Install [refactor-circus/keen](https://github.com/Refactor-Circus/Keen) as well
to keep History (see [History](#history)).

```bash
composer require refactor-circus/atrium
```

Define Atrium's gate and Impex appears in the sidebar:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewAtrium', fn ($user) => $user->is_admin);
```

Publish Atrium's assets once:

```bash
php artisan vendor:publish --tag=atrium-assets
```

Then set the single switch:

```php
// config/impex.php
'ui' => ['enabled' => true],
```

Setting it to `false` removes Impex from the dashboard and leaves the JSON API
serving. **Gate this carefully**: the dashboard renders every payload that has
crossed your application boundary.

## Screens

| Screen | What it shows |
|---|---|
| Runs | Filterable by status, flow, trigger, owner and tag. Cursor paginated. |
| Run detail | Definition list, error panel, cancel and retry, a signal form shown only while the run is waiting, the step timeline distinguishing forward from rollback, owners, and messages. |
| Messages | The ledger in both directions, filterable by direction and channel. |
| Message detail | Headers, body preview, signature status, and a link to the owning run. |
| Flows | The catalogue with a trigger form per flow. |
| Channels | Every channel, inbound and outbound, configured and stored: its direction, transport, receive path (inbound only), status, where it is defined, and whether it verifies signatures. A **New channel** button for those the `ChannelPolicy` lets `create`. |
| Channel detail | Status, body policy, signature and source. A stored channel can be changed (transport, status, body policy, options as JSON, a new signing secret — left blank, the current one is kept), its secret rotated (the new secret is shown once, in the flash), and deleted; a configured one is read-only. |
| Subscriptions | Subscriptions filterable by stream and status, with subscriber, endpoint (or feed-only), failures and last delivery. |
| Subscription detail | Topics, selection, format, endpoint, cursor, backoff and last error; the latest deliveries, each linking to the ledger row it sent; pause, resume, replay and ping. |

Status filters are driven from `RunStatus::cases()`, so the dashboard can never
offer a status the API would reject.

The screens are built from Atrium's components only (`x-atrium::description-list`
for details, `x-atrium::flash` for the status and first error after an action,
`x-atrium::status-dot` for states) and the utilities Atrium's stylesheet
contains. Impex ships no stylesheet and no Blade components; to restyle the
pages, publish `impex-views` or retheme Atrium.

## Subscriptions

The Subscriptions item sits in the Impex sidebar group, shown to those the
`SubscriptionPolicy` lets `viewAny`. A subscription's page offers, to those
who may `update` it:

| Control | Does |
|---|---|
| Pause | Holds deliveries. Events keep collecting. |
| Resume | Shown instead of Pause for a paused or disabled subscription. Clears the failure count and backoff, and delivers what is pending. |
| Replay | Delivers again from the event id entered. |
| Ping | Push subscriptions only. Sends a signed, empty ping and flashes how the endpoint answered. |

Each calls the same Action as the API (`UpdateSubscriptionAction`,
`PingSubscriptionAction`). Subscribers and new subscriptions are managed over
the [API](09-api.md#subscribers) and [MCP](10-mcp.md). See
[Subscriptions](19-subscriptions.md).

## Screen extensions

Every screen above declares itself to Atrium's screen registry, so another
package can add a tab or a section to it from its `boot()` through
`Atrium::screens()`, without Impex knowing it exists:

| Key | Kind | Record, or row of `row.actions` |
|---|---|---|
| `impex.runs` | list | each run |
| `impex.run` | detail | the run |
| `impex.messages` | list | each message |
| `impex.message` | detail | the message |
| `impex.flows` | list | the flow's stored `FlowOverrideModel`, else an unsaved one naming its slug |
| `impex.channels` | list | the stored `ChannelModel`, else an unsaved one naming the configured channel |
| `impex.channel-create` | detail | none |
| `impex.channel` | detail | the stored channel, else an unsaved one naming it |
| `impex.subscriptions` | list | each subscription |
| `impex.subscription` | detail | the subscription |

Each has the standard slots: `header.actions` (after the page's own actions),
`before` and `after` (around the content), plus `aside` (a right-hand column,
shown only when it has a visible section) on a detail screen or `row.actions`
(in each row's last cell) on a list. An open registered tab's panel replaces
the page; only `header.actions` stays. With nothing registered the pages are
unchanged. The unsaved stand-ins have `exists` false; check it before treating
a row as stored.

A `permission()` on an extension is asked as Impex's own pages ask it, through
`ScreenAccess::allows()` (operators included), of the record or row, or of the
screen's model class when there is none.

## Run links on other packages' screens

Impex links any row whose record is an Impex run to that run's page here. It
adds the link to other packages' screens only through config, as one
`impex.run-link` section per entry of `impex.atrium.run_link.screens` (screen
key => slot name):

```php
// config/impex.php
'atrium' => [
    'run_link' => [
        'screens' => [
            'showroom.transfers' => 'row.actions', // the default
            // 'acme.jobs' => 'aside',
        ],
    ],
],
```

In a row slot such as `row.actions` the link is drawn in each row whose model
is a run, beside the host's own cells; in another slot it uses the screen's
record. It reads "Open run", with the flow name in its `aria-label`. A screen
nobody declares never renders. `[]` places the link nowhere; a published config
without the key gets the default.

The link shows only when all of these hold:

| Condition | Otherwise |
|---|---|
| The row (or the screen's record) is a saved `RunModel` | Any other row, or an unsaved run, gets nothing, and costs no query. |
| Impex's Atrium plugin is authorized for the request (its `impex.atrium.features` are on) | Hidden, as Impex's own pages answer 404. Checked once per request. |
| `ScreenAccess::allows('view', $run)`, operators (`impex.atrium.show_all`) included | Hidden for a run the viewer may not view. |
| The `atrium.impex.runs.show` route is loaded | Hidden, so a route-cache or plugin-loading mismatch does not fail the host page. |

Each entry must map a non-empty string screen key to a non-empty string slot
name. A malformed entry, or one Atrium rejects, throws `InvalidArgumentException`
at boot in the `local` and `testing` environments. Anywhere else, such as
production, it is reported once with `report()` and skipped; the other entries
still register and the app boots. Nothing is registered without Atrium, with an
Atrium older than the screen registry, or with `impex.ui.enabled` off.

## History

History comes from [refactor-circus/keen](https://github.com/Refactor-Circus/Keen),
which attaches it to every Atrium screen, Impex's included. Impex renders no
history of its own and its sidebar group has no History item; install Keen to
keep History. Run steps are not part of it: the step timeline records
execution, not changes.

## Who sees what

With `impex.authorization` on, the screens ask the same policies as the JSON
API: a nav item, widget, card or button is shown only when its action would be
allowed, the action is refused (403) otherwise, and lists, counts, widgets and
search cover only the runs the user owns. Use `@impexCan('cancel', $run)` in
your own views for the same check; `RefactorCircus\Impex\Atrium\ScreenAccess::allows()`
lets operators through and otherwise asks Atrium's shared
`ScreenAccess::allows('impex', ...)`.

Runs with no owner, such as scheduled and channel runs, are therefore hidden
from everyone. Make some users **operators** to see and handle them:

```php
// config/impex.php
'atrium' => [
    'show_all' => 'impex-operator', // false (default), true, or a Gate ability
],

Gate::define('impex-operator', fn ($user) => $user->is_admin);
```

`true` makes everyone past Atrium's gate an operator; a string makes those the
Gate ability allows operators. An operator sees every run and message and may
use every Impex control on them — on the dashboard only. The JSON API and MCP
tools never consult `show_all`.

## Widgets

Impex contributes three widgets to Atrium's picker:

| Widget | Shows |
|---|---|
| Run status | How many runs sit in each state, each linking to a filtered list. |
| Recent failures | The five most recently failed runs. |
| Message volume | Inbound and outbound counts over the last day. |

These are **offered**, not placed. A widget appears on a dashboard only when
someone adds it.

## Settings

Impex adds a read-only panel to Atrium's settings showing the effective
`retention`, `limits` and `artifacts` config and the message preview size.
Change them in `config/impex.php`.

## Search

Impex registers a search source with Atrium, so the command palette matches runs
by flow name or by run id prefix.

## Without Atrium

Only `RefactorCircus\Impex\Atrium` uses Atrium (an arch test checks it), and
Impex reaches it only while `RefactorCircus\Atrium\Atrium` exists and
`impex.ui.enabled` is on. `composer test` runs `composer test:without-atrium`,
which hides Atrium from the autoloader in process and checks that Impex boots
and serves its API and MCP. That run cannot see a classmap or Composer `files`
leak, so the `without-atrium` CI lane, which uninstalls Atrium and runs the
same suite, is the authoritative no-Atrium proof. Only the workflow run
verifies it.
