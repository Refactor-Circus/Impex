# Drop the paratest conflict pin (Impex)

Ticket: [#13](https://github.com/Refactor-Circus/Impex/issues/13)

## Goal
Remove the temporary `brianium/paratest` conflict pin from `composer.json` and require a Pest release that brings a fixed paratest, so `pest --parallel` works without the pin.

## Current state
The `composer.json` edit is already in the working tree (uncommitted): `pestphp/pest` is `^5.3.4` and the `conflict` key is gone. Its diff against `main` holds only those two changes. Criteria 1 and 2 are therefore met; what remains is the update, verification and commit. `composer.lock` is gitignored (untracked), so the lock is never part of the change and CI resolves dependencies fresh; the `^5.3.4` floor is what guarantees the paratest version there.

## Acceptance criteria
1. `composer.json` has no `conflict` key (it would be empty).
2. `composer.json` requires `pestphp/pest` at `^5.3.4`.
3. In the installed set, `composer show pestphp/pest brianium/paratest` reports `pestphp/pest` >= 5.3.4 and `brianium/paratest` >= 7.27.4, and `composer why-not brianium/paratest 7.27.4` reports no blocker.
4. `composer test` exits 0. It runs `analyse`, `lint:check`, `test:types` (`pest --type-coverage --min=100`) and `test:unit`.
5. `composer test:unit` (`vendor/bin/pest --parallel`) exits 0. `composer test` already includes it; this is named separately as the direct check that the pin removal works.
6. `git diff --name-only main...HEAD` lists only `composer.json` and files under `docs/agent/specs/2026-10-28-0130-drop-paratest-conflict-pin/`. `composer.lock` is ignored and must not appear.

## Design
Edit `composer.json` only (already done), then `composer update pestphp/pest brianium/paratest -W`. No code, config or test changes. Transitive updates forced by `-W` (pest plugins, phpunit and the like) are allowed. After the update, run the full `composer test` so the type-coverage gate and the parallel run are both exercised, and record the resolved `pestphp/pest`, `brianium/paratest` and any other moved versions in the PR description.

## Out of scope
Any change to config or code. Transitive updates forced by `-W` are fine. Raising a direct dev constraint (for example `pest-plugin-laravel` or `pest-plugin-type-coverage`) is out of scope: if the update cannot resolve without one, stop and raise it as a new decision rather than making it quietly.

## Risks and open questions
Open questions: none. Risk: `-W` may move pest plugins or phpunit, which could regress the type-coverage gate; criterion 4 catches this.
