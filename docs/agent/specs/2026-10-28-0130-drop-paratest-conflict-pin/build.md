# Build: Drop the paratest conflict pin (Impex)

## Group 2

Rework 1: Redo the composer.json edit in the job worktree. Commit `0610bd3` on `ring-master/2026-10-28-0130-drop-paratest-conflict-pin--g2`.

### Files
- `composer.json`: removed the `conflict` key (`brianium/paratest: >=7.26.1`) and raised `pestphp/pest` from `^5.3.1` to `^5.3.4`. The diff matches `stash@{0}` exactly.

### Checks (tasks 1.1 to 1.6)
- 1.1: `composer.json` has no `conflict` key and requires `pestphp/pest` `^5.3.4` (criteria 1, 2).
- 1.2: the worktree is installed from `composer.local.json` (sibling path repositories), so the update ran through the package's own script: `composer local:update -- pestphp/pest brianium/paratest -W`, which regenerates `composer.local.json` from the edited `composer.json` and runs `composer update pestphp/pest brianium/paratest -W` against it. It resolved without raising any direct dev constraint.
- 1.3: `composer show pestphp/pest` reports v5.3.4; `composer show brianium/paratest` reports v7.27.4; `composer why-not brianium/paratest 7.27.4` reports it is already installed, no blocker (criterion 3).
- 1.4, 1.5: not run here; the gate runs `composer test:unit` and `composer test` after the build (criteria 4, 5).
- 1.6: `git diff --name-only main...HEAD` lists only `composer.json`; `composer.lock`, `composer.local.json` and `composer.local.lock` are gitignored and absent (criterion 6).

### Resolved versions (for the PR description)
| Package | Before | After |
| --- | --- | --- |
| `pestphp/pest` | v5.3.1 | v5.3.4 |
| `brianium/paratest` | v7.26.0 | v7.27.4 |
| `phpunit/phpunit` | 13.4.1 | 13.4.5 |
| `phpstan/phpdoc-parser` | 2.3.6 | 2.3.9 |

The `refactor-circus/*` path packages (atrium, cortex, keystone, pennantplus, ringside) were relabelled to the current commits of their sibling checkouts by `-W`; they are symlinks, so their code did not change. CI resolves them from VCS.

### Tests
No test files: the spec allows no code or test changes, so the checks above are the tests. The suite is left to the gate.

### Deviations
- The update ran through `composer local:update -- ...` instead of a bare `composer update ...`, because the worktree's install comes from `composer.local.json` and the runner refuses the `COMPOSER=...` prefix. A bare update on `composer.json` (no lock present) would have re-resolved every package and replaced the sibling symlinks with VCS clones. Same packages and flags; only the manifest differs.

## Group 1

Update dependencies and verify. Commit `494da22` on `ring-master/2026-10-28-0130-drop-paratest-conflict-pin--g1` (retry: the commit from the earlier attempt is kept; this pass re-verified it and changed no code).

### Files
- `composer.json`: removed the `conflict` key (`brianium/paratest: >=7.26.1`) and raised `pestphp/pest` from `^5.3.1` to `^5.3.4`. The diff is the same as the one on the g2 branch.

### Checks (tasks 1.1 to 1.6)
- 1.1: `composer.json` has no `conflict` key and requires `pestphp/pest` `^5.3.4` (criteria 1, 2). The regenerated `composer.local.json` carries the same constraint and no `conflict`.
- 1.2: the update resolved without raising any direct dev constraint. Pest v5.3.4 itself requires `brianium/paratest ^7.27.4` and `phpunit/phpunit ^13.4.5`.
- 1.3: `composer show` reports `pestphp/pest` v5.3.4 and `brianium/paratest` v7.27.4. `composer why-not brianium/paratest 7.27.4` reports "already installed", so nothing blocks it (criterion 3).
- 1.4, 1.5: not run here, as the build instructions say; the gate runs `composer test:unit` and `composer test` after the build (criteria 4, 5).
- 1.6: `git diff --name-only main...HEAD` lists only `composer.json`. `composer.lock`, `composer.local.json` and `composer.local.lock` are gitignored and do not appear (criterion 6).

### Resolved versions (for the PR description)
| Package | Before | After |
| --- | --- | --- |
| `pestphp/pest` | v5.3.1 | v5.3.4 |
| `brianium/paratest` | v7.26.0 | v7.27.4 |
| `phpunit/phpunit` | 13.4.1 | 13.4.5 |
| `phpstan/phpdoc-parser` | 2.3.6 | 2.3.9 |

### Tests
No test files: the spec allows no code or test changes, so the checks above are the tests. The suite is left to the gate.

### Deviations
- Same as Group 2: the worktree installs from `composer.local.json`, so the update ran against that manifest through `composer local:update` with the same packages and `-W`.
