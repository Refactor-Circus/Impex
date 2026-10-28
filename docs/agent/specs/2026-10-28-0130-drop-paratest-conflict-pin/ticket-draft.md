# Drop the paratest conflict pin (Impex)

## Problem

`composer.json` carries a temporary `"conflict": {"brianium/paratest": ">=7.26.1"}` entry that keeps paratest below the releases that broke `pest --parallel`. Pest 5.3.4 requires paratest ^7.27.4, which fixes it, so the pin is no longer needed.

## Desired outcome

- The `conflict` key is removed from `composer.json` (it is empty afterwards).
- `pestphp/pest` is required at `^5.3.4`.
- `pest` and `paratest` are updated; the full suite passes, including `--parallel`.

## Acceptance hints

- `composer test` passes.
- `vendor/bin/pest --parallel` passes.
- `composer.json` has no `conflict` entry for `brianium/paratest`.

## Out of scope

Any other dependency, config or code change. Part of campaign #1 across Atrium, Cortex, Impex, PennantPlus, Polycart, Roster and Showroom; Keystone did the same in its PR #8.
