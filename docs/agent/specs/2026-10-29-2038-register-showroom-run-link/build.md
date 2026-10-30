# Build: Register Showroom run link

## Group 1 (salvaged)

**Found.** No index lock and no merge or rebase in progress. The first build agent left uncommitted work for tasks 1.1 to 1.4 and most of 1.5: `composer.json` (Atrium moved to `require-dev` at `dev-main`, its `suggest` text naming the 0.1.0 screen-extensions registry release and Keen for History, `conflict` `"refactor-circus/atrium": "<0.1.0"`, the `test:without-atrium` script called by `composer test` after `@test:unit`), the arch rule `only the Atrium layer uses Atrium`, `TestHostScreenController` with the `test-host` view, the `test-host` route in `tests/TestCase.php::defineRoutes()`, `ResetsScreenExtensions` on every Atrium-booting suite in `tests/Pest.php`, `tests/Feature/ComposerTest.php`, and `tests/WithoutAtrium/` (bootstrap, `Pest.php`, test case, `WithoutAtriumTest.php`). `composer.local.json` (git-ignored) already mirrored the composer changes by hand. It was not regenerated.

**Completed and committed.**
- `phpunit.without-atrium.xml` (bootstrap `tests/WithoutAtrium/bootstrap.php`, one `WithoutAtrium` suite, its own cache directory).
- `phpunit.xml.dist`: a comment saying `tests/WithoutAtrium` is deliberately not a suite. No configured suite covers that folder, so it is already left out.
- `.github/workflows/tests.yml`: a `without-atrium` job. It installs, removes every root dev dependency that requires Atrium directly or indirectly (computed with `composer why --recursive`; today that is `refactor-circus/cortex`), runs `composer remove --dev refactor-circus/atrium --no-update` and then `composer update`, checks that `vendor/refactor-circus/atrium` is gone, and runs `composer test:without-atrium`. It skips `composer run prepare` because `testbench.yaml` names `AtriumServiceProvider`.
- The whole group, including the first agent's files above.

**Outside the listed files.** I also committed `tests/Feature/Ui/TestHostScreenTest.php` (the test-host smoke test that task 1.1 asks for) and `workbench/resources/views/test-host-marker.blade.php` (the marker view it renders). Both belong to this group, but the group's file list did not name them. `AGENTS.md` has an unrelated uncommitted change, which I left alone.

**Conflict constraint.** The constraint is `<0.1.0`, as the salvage instructions require. Task 1.2's text says `<1.0.0` if Atrium is untagged. The PR has to record which constraint shipped.

**Left.** Task 1.6 (`vendor/bin/pest` all green) was not run, because the salvage instructions say the gate runs the suite. Only `pint --test` was run on the group's PHP files, and it passed. The CI lane is verified only by a workflow run.
