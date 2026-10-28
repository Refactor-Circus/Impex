# Spec review

## Pass 1

Critics: scope, risk, testability. No design questions. Fixed in `spec.md` (all minor or nit):

- Added "Current state": `composer.json` edit already in the working tree; `composer.lock` is gitignored, so CI resolves fresh and the `^5.3.4` floor guarantees paratest.
- Criterion 3 now checks with `composer show` and `composer why-not brianium/paratest 7.27.4`.
- Criterion 4 names what `composer test` runs (analyse, lint, type coverage, parallel unit); criterion 5 kept as the direct parallel check.
- Criterion 6 defined as `git diff --name-only main...HEAD`.
- Design/Out of scope: transitive `-W` updates allowed; bumping a direct dev constraint is out of scope and must come back as a decision; resolved versions go in the PR body.
