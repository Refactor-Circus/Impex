VERDICT: PASS

Gate results (all started and passed):
- composer test: phpstan 0 errors, pint passed, pest 476 passed (2206 assertions), without-atrium suite 6 passed.
- composer lint:check: pint passed.
- composer exec ringside check: passed.

Criteria mapping (file level; names where known):
1-2. tests/Feature/Ui/ScreenRegistrationsTest.php, src/Atrium/ScreenRegistrations.php, tests/Feature/NoShowroomReferenceTest.php
3. tests/Feature/Ui/RunLinkVisibilityTest.php (shows on saved run; hides on non-run, unsaved, plugin unauthorized, screen access refuses, route absent; constant queries; no relations)
4. resources/views/ui/screens/run-link.blade.php, tests/Feature/Ui/StylesTest.php, tests/Feature/Ui/RunLinkSectionTest.php
5. workbench/app/Http/Controllers/TestHostScreenController.php, tests/Feature/Ui/TestHostScreenTest.php
6. tests/Feature/Ui/RunLinkSectionTest.php, tests/PennantPlus/RunLinkPennantTest.php
7. tests/Feature/Ui/DeclaredScreensTest.php, src/Atrium/Screens.php, controllers
8. tests/Feature/Ui/ScreenAccessClosureTest.php
9. tests/Feature/Ui/ScreenLayoutTest.php
10. tests/Feature/NoHistoryUiTest.php, tests/Feature/HistoryNavigationTest.php, tests/Feature/DocsTest.php
11. AuditTrailTest deleted, HistoryNavigationTest replaced, tests/Pest.php and TestCase.php changed (see changed files)
12. tests/Feature/ComposerTest.php (requires keystone only; suggests atrium; installs atrium for dev; refuses older atrium; never depends on showroom)
13. tests/ArchTest.php
14. tests/WithoutAtrium/WithoutAtriumTest.php, phpunit.without-atrium.xml, .github/workflows/tests.yml (CI lane is authoritative; verified only by workflow run)
15. tests/Feature/DocsTest.php, README.md, CHANGELOG.md, docs/11-dashboard.md, Boost skill
16. composer test passes including test:without-atrium
