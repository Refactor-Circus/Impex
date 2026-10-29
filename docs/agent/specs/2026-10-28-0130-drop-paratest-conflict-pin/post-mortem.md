# Post-mortem: Drop the paratest conflict pin (Impex)

Spec `2026-10-28-0130-drop-paratest-conflict-pin` · ticket [#13](https://github.com/Refactor-Circus/Impex/issues/13) · PR [#14](https://github.com/Refactor-Circus/Impex/pull/14), merged 2026-10-28.

## Metrics

| Rounds | Decisions | Deviations | Escalations | Findings (blocker/major/minor/nit) |
| --- | --- | --- | --- | --- |
| 1 | 3 | 1 | 1 | 0 / 0 / 0 / 0 |

Factory runs: `wf_4adc734c-844` escalated at prepare (round 0); `wf_a15d7a49-934` approved (round 1).

## Root causes

1. **Tooling.** The first factory run escalated at prepare because `composer.local.json` was stale and main had an uncommitted `composer.json` edit. That cost decision D-003 (stash `composer.json` and let the build redo the edit) plus a second run.
2. **Spec gap.** The spec was written against an uncommitted working-tree edit and called criteria 1 and 2 already met. It never said the isolated build had to redo that edit. It also said to run a bare `composer update`, but the worktree installs from `composer.local.json`, so the build had to switch to `composer local:update` (deviation).
3. **Tooling.** Build groups 1 and 2 did the same change: group 1 re-verified commit 494da22 without changing code, and group 2 redid the edit as 0610bd3. The work was duplicated, but the result was identical.

## Human review delta

- The PR had no human review comments. The only human input was three RingMaster gates: D-001 (ticket as drafted), D-002 (spec approved) and D-003 (stash `composer.json`).
- Criterion 6 allowed spec-folder files in the branch diff. Those files are untracked on main, so `git diff --name-only main...HEAD` can never list them. The gate passed, but the criterion's wording doesn't match the diff.
- Pre-flight should have caught the dirty main and the stale `composer.local.json` before the first run.

## Pending proposals (awaiting the user)

Both proposals target this project (`docs/agent/standards/`, discovery mode `ask`). None has been accepted or rejected yet.

1. **`tooling/composer-local`** (add). In a checkout or worktree installed from `composer.local.json`, change dependencies only through `composer local:install` / `composer local:update -- <pkgs> -W`, never a bare `composer update`. Rebuild after every `composer.json` edit, never hand-edit the generated file, and have specs name the `local:*` command. Evidence: decision.md (escalated at prepare: stale composer.local.json), the build.md Group 2 deviation, and the spec.md Design step that used a bare `composer update`.
2. **`specs/baseline-is-main`** (add). Specs describe current state and criteria against committed `main`, never against uncommitted edits. Any other uncommitted change gets a decision at spec approval. Changed-files criteria list exactly the files the branch commits, not the untracked spec-folder files. Evidence: spec.md "Current state" (criteria 1 and 2 called already met), criterion 6, D-003, and round-1/gate.md criterion 6.

Full text: `proposals/tooling-composer-local.md` and `proposals/specs-baseline-is-main.md` in this folder (from workflow run `wf_e6a2fc1d-a5a`).

## Rejected proposals

None yet.
