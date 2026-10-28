# Drop the paratest conflict pin (Impex) — Shaping Notes

## Scope

Dependency housekeeping in `composer.json` only (ticket #13, campaign #1):

- Remove the `conflict` key (`brianium/paratest: >=7.26.1`); it is empty afterwards.
- Require `pestphp/pest` at `^5.3.4` (needs paratest `^7.27.4`).
- `composer update` for pest and paratest; the suite passes, including `vendor/bin/pest --parallel`.

Out of scope: any other dependency, config or code change.

## Decisions

- D-001: ticket created as drafted (issue #13).
- Atrium, Cortex and PennantPlus (required siblings) already merged their part; their checkouts are up to date.

## Context

- Keystone did the same in its PR #8.
- No UI surface, no product docs alignment needed.

## Standards

None apply: no code, model, action or HTTP change. Done means `composer test` passes.
