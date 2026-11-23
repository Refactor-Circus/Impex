# Dashboard

Impex ships no UI. Since v2.0.0 it is a JSON API and an MCP server only, and
the optional dashboard is [Atrium v2](https://github.com/Refactor-Circus/Atrium).

## Atrium v2

Atrium v2's Impex integration renders the screens, widgets, search and the
settings panel that Impex 0.x drew itself: runs, a run's steps, owners and
signals, the message ledger, flows, channels and subscriptions. It calls
Impex's [HTTP API](09-api.md) through the generated
`@refactor-circus/impex-client` TypeScript package, so the dashboard and the
API can never disagree.

Install and configure the integration on Atrium's side. Its settings live under
`atrium.integrations.impex.*` in Atrium's config, and Impex needs no setting
of its own beyond keeping its API on (`impex.routes.enabled`, the default). See
Atrium v2's documentation for the setup.

## What it reads

The integration uses the same endpoints as any other API client, plus three
that exist for dashboards:

| Endpoint | Feeds |
|---|---|
| `GET impex/stats` | The run status and recent failures widgets, the message volume widget and the active-runs badge |
| `GET impex/search?q=` | Atrium's search: runs by flow name or run id prefix |
| `GET impex/settings-summary` | The read-only settings panel: retention, limits, artifacts and preview size |

See [Dashboard endpoints](09-api.md#dashboard-endpoints) for their shapes and
[OpenAPI and the TypeScript client](09-api.md#openapi-and-the-typescript-client)
for the client.

## Who sees what

The dashboard sees what the API lets the signed-in user see.
`impex.routes.middleware` decides who may call the API at all. With
`impex.authorization` on, every call is checked against the policies in
`impex.policies`, and lists, counts and search cover only the runs the user
owns and those runs' messages. See [Ownership](08-ownership.md#authorizing-the-api).

Runs with no owner, such as scheduled and channel runs, are therefore hidden
from everyone while authorization is on. There is no operator mode that shows
every run yet. A host that needs one can serve the API with
`impex.authorization` off on a separate, operator-only route stack, or wait for
a follow-up release that adds a policy ability for it.

> **Gate the API carefully.** It reads every payload that has crossed your
> application boundary.

## Upgrading from Impex 0.x

The Atrium plugin, its views and translations, its config sections, its
Pennant feature, its Blade directive and its publish tags were removed in
v2.0.0. The [CHANGELOG](../CHANGELOG.md)'s v2.0.0 upgrade note lists each one
and where its setting went.
