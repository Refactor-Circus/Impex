# Tasks: Drop the paratest conflict pin (Impex)

## Group 1: Update dependencies and verify
depends on: none
files: composer.json
criteria: 1, 2, 3, 4, 5, 6

- [x] 1.1 Write the checks first: confirm `composer.json` has no `conflict` key and requires `pestphp/pest` `^5.3.4` (already in the working tree), and that `composer test` is the gate to pass
- [x] 1.2 Run `composer update pestphp/pest brianium/paratest -W`; if it cannot resolve without raising a direct dev constraint, stop and raise a new decision
- [x] 1.3 Check `composer show pestphp/pest brianium/paratest` (pest >= 5.3.4, paratest >= 7.27.4) and `composer why-not brianium/paratest 7.27.4` reports no blocker
- [x] 1.4 Run `composer test:unit`; all green
- [x] 1.5 Run `composer test`; exits 0
- [x] 1.6 Check `git diff --name-only main...HEAD` lists only `composer.json` and files under the spec folder; `composer.lock` must not appear. Record the resolved pest, paratest and other moved versions for the PR description

## Group 2: Rework 1: Redo the composer.json edit in the job worktree
depends on: none
files: composer.json
criteria: 1, 2, 3, 4, 5, 6

- [x] 2.1 Prepare failed on a stale `composer.local.json` in the Impex checkout; it has been regenerated from `composer.json`, so setup installs again. The `composer.json` edit is no longer in the main checkout (it is in `stash@{0}`, "drop-paratest-conflict-pin: composer.json pre-build edit"): make it in the job worktree as task 1.1 describes (remove the `conflict` key, require `pestphp/pest` `^5.3.4`), then run the rest of group 1 (tasks 1.2 to 1.6)
