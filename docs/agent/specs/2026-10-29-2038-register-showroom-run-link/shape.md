# Register Showroom run link — Shaping Notes

## Scope

Impex owns the link from a Showroom transfer row to its Impex run page. Today Showroom's transfers view checks `Route::has('atrium.impex.runs.show') && class_exists(Impex\Atrium\ScreenAccess::class)` inline. After this change, Impex registers it through Atrium's screen extensions registry.

In scope:

- **Run-link section.** Register a section on the `showroom.transfers` row slot, declared by Showroom's spec, that renders the run's flow name as a link to `atrium.impex.runs.show`. Visible when `Impex\Atrium\ScreenAccess::allows('view', $run)`. The section's record is the row's run.
- **Impex's own screens.** Drop the inline `<x-atrium::audit-trail>` from `ui/runs/index`, `ui/runs/show` and `ui/messages/show`. Declare their audited subjects for Keen.

## Decisions

- D-001: the registry must support row-level slots (one section render per table row, with that row's record). This requirement belongs to the Atrium spec: confirm it there, or fall back to a per-row component that reads the registry.
- D-002: Roster's and Showroom's transfers pages and nav are section B and out of scope. Those packages keep their Impex flows (behaviour, not UI).
- D-003: settled. Atrium's registry supports per-row slots (Atrium D-006). The run link is a row section on `showroom.transfers`. (User, 2026-10-29.)

## Context

- **Depends on:** Atrium `screen-extensions-registry`. Must merge with or before Showroom `declare-screen-extensions`.
- **References:** see `references.md`.

## Standards

- architecture/dependencies: the add-on registers into the host by key.
- atrium/components: `x-atrium::*` only.
- http/authorization: Impex `ScreenAccess` decides visibility.
- testing/test-layers: a feature test with Showroom as a dev dependency, or a fake host screen.

## Addendum (2026-10-29): every screen declares slots and tab bar

User decision (Atrium D-013): every Atrium screen in this package declares itself with `Screen::make("{package}.{screen}")`, renders the Atrium tab bar (only visible once at least 2 tabs are visible), and declares the standard slots: `header.actions`, `before` and `after` on all screens; `aside` and `history` (when audited) on detail screens; the `row.actions` row slot on list screens. Screens without built-in tabs get a default `overview` tab. With nothing registered, every existing feature and browser test passes unchanged. A feature test per screen registers a tab and a section in each standard slot and asserts that they render.
Impex screens (about 10): runs index and show, messages index and show, flows, channels index, create and show, subscriptions index and show.

## Addendum (2026-10-29): no package knows another (Atrium D-015 to D-019)

User principle (Atrium D-017): no package has knowledge of another. Companion packages are suggested, never required. Integrations are done through simple config. Consequences for this spec:
- Remove `refactor-circus/atrium` from `require`, and add it to `suggest` (D-018: Keystone is the only allowed requirement). The Atrium plugin, its routes, views and `Atrium\*` classes load only when Atrium is installed (`registerAtriumPlugin()` already checks for this). Code outside `src/Atrium` must not reference Atrium classes. An arch test enforces this, and the suite runs once without Atrium (a CI lane or a test mode that removes it) to prove the package boots and its HTTP API and MCP work.
- Screens declare no audit subject (that concept is removed from Atrium). Keen attaches History by wildcard from its own config, and the screen record is the subject.
- Stop calling `historyNavItem()` (removed from Atrium, D-016); Keen adds History nav items.
- The run link is placed through config (D-019): `config/impex.php` `atrium.run_link.screens` defaults to `['showroom.transfers' => 'row.actions']`. The row record must be an Impex run, and the section renders nothing for any other row model.
