## Round 1

Changed in `spec.md`; Q-1 and Q-2 and criterion 15's scope left as they were.

- Goal: restructured into three workstreams (run link, screen declarations, decoupling from Atrium) and stated they ship together because of the grouped merge with Atrium, Keen and Showroom. Why: scope was hidden in one paragraph.
- Q-1 not settled and criterion 15 not cut, because Q-1 is an open question to leave alone. Added to criterion 15 the Testbench discovery, loader-prefix, loaded-providers, workbench exclusion, named MCP tool via `mcpTool()` and `composer update` CI fixes.
- Criterion 2: missing config key falls back to the default, explicit `[]` registers nothing; per-entry validation and catch (log, rethrow in debug), skipped; guard on `ScreenExtensions`; tests added. Added Q-3 (malformed entry) with recommendation, which the criteria implement.
- Criterion 3: added the `Route::has` check, per-request memoization, a ~25-row constant-query test, and the per-case false list.
- Criterion 4: exact-anchor render test with escaping. Criterion 6: named the `impex.atrium.features` gate, the default-suite test and the Pennant variant.
- Criterion 7: explained the flow/channel stand-ins (Atrium D-013), fillable/`forceFill`, no events or queries test, dataset fixtures, and defined the slot-absence and `?tab=` assertions.
- Criterion 9: named `ScreenLayoutTest` with `assertSeeInOrder` markers, an aside-empty test, and a zero-registered check.
- Criterion 10: scan list fixed, Keen assertion on `docs/11-dashboard.md`, Keen advice for lost History.
- Criterion 12 now holds the single no-Showroom check across `require`, `require-dev` and `suggest`, plus the minimum Atrium version; criterion 14 merged into it and criterion 5 points to it. The `src/` grep excludes comments.
- Criterion 16: lists every doc file (README, `docs/11-dashboard.md`, Boost skill, `CLAUDE.md`, CHANGELOG), adds the republish and Keen notes, a CHANGELOG string test, and marks the PR test list as manual.
- Out of scope: added Atrium's registry, the `history` slot, and other hosts. Risks: added the older-Atrium case.
- Placeholder: the minimum Atrium version is described as "the release of Atrium's screen-extensions registry"; the exact number needs filling in when known.
