# Specs Start From Main

A spec describes current state and acceptance criteria against committed `main`, never against uncommitted edits in the main checkout, because the build runs in an isolated worktree cut from `main`.

## Current state

- Describe what `main` holds. If a draft edit sits uncommitted in the working tree, say so and list it under Design as work the build must redo in its worktree. Never mark a criterion as already met because of it.
- Before tasks run, the main checkout is clean apart from the job's own untracked spec folder. Any other uncommitted change gets a decision (commit it, or stash it and let the build redo it) at spec approval, not when the build stops on it.

## Changed-files criteria

- Check a changed-files criterion with `git diff --name-only main...HEAD` on the job branch. Spec-folder files are untracked on `main` and never appear in that diff, so do not list them as allowed branch files. State the criterion as the exact set of files the branch commits.
- Gitignored files (`composer.lock`, `composer.local.json`, `composer.local.lock`) never appear and need no mention.

## Example

```markdown
## Current state
`main` still has the `conflict` pin and `pestphp/pest ^5.3.1`. A matching edit is stashed
(D-003); the build redoes it in the job worktree.

## Acceptance criteria
6. `git diff --name-only main...HEAD` lists only `composer.json`.
```
