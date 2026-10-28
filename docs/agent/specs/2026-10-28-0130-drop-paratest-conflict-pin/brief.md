Risk: low

## What changed
`composer.json` only: the `conflict` key (`brianium/paratest >=7.26.1`) is removed and the `pestphp/pest` dev constraint is raised from `^5.3.1` to `^5.3.4`. No code, config or test changes. `composer.lock` is gitignored, so it is not part of the diff.

## Why
The conflict pin was a temporary workaround for a paratest bug that broke `pest --parallel`. Pest 5.3.4 pulls in a fixed paratest (7.27.4), so the pin can go (ticket #13). The `^5.3.4` floor is what guarantees the fixed paratest in CI, which resolves dependencies fresh. Decision D-003: the dirty `composer.json` in main was stashed and the build redid the edit.

## Look at
- `composer.json:34`: the `pestphp/pest` floor `^5.3.4`; this is the only guard against resolving a broken paratest.
- `composer.json:166`: the `conflict` block is gone and the `repositories` array now ends the file; check the JSON is still well formed.

## Tested
Round 1 gate: PASS.
- Criteria 1 and 2: the diff shows the `conflict` key removed and `^5.3.4` set.
- Criterion 3: installed pest v5.3.4 and paratest v7.27.4; `composer why-not brianium/paratest 7.27.4` reports no blocker.
- Criteria 4 and 5: `composer test` exits 0 (phpstan 0 errors, pint, type coverage 100%, pest `--parallel` 363/363).
- Criterion 6: the branch diff lists only `composer.json`.

## Risks
Dev dependency change only, with no runtime or consumer impact. CI resolves fresh, so transitive moves could differ from the local lock; the `^5.3.4` floor limits that. No findings left (nits: 0).
