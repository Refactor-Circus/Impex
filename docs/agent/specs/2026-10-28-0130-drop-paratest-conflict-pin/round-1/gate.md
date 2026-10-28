VERDICT: PASS

Gate (worktree Impex--2026-10-28-0130-drop-paratest-conflict-pin):
- composer test: passed (phpstan 0 errors, pint, type coverage 100%, pest 363/363)
- composer lint:check: passed
- ringside check: passed

Criteria:
1. No conflict key: composer.json diff (only changed file).
2. pestphp/pest ^5.3.4: composer.json; installed v5.3.4.
3. Installed pest v5.3.4, paratest v7.27.4; `composer why-not brianium/paratest 7.27.4` reports already installed, no blocker.
4. composer test exit 0.
5. composer test runs test:unit (pest --parallel), 363 tests passed.
6. git diff --name-only main...branch lists only composer.json (spec folder files are uncommitted in main checkout).

changed_files: composer.json
