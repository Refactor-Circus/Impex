# Decisions

Earlier D-ids are recorded in shape.md.

## D-020: Suite: proving the package runs without Atrium, and refusing an Atrium too old for the screen registry
- Choice: Prove it both ways: a WithoutAtrium suite inside `composer test`, and a CI lane with Atrium uninstalled. Every package that renders Atrium screens adds a composer `conflict` on refactor-circus/atrium versions older than the screen-extensions registry (Atrium PR #16).
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: user, cross-spec decision (2026-10-29)

## D-021: Suite: malformed integration config entries (screen maps, slots) at boot
- Choice: Throw InvalidArgumentException in local and testing. In production, report once, skip the entry and keep booting (same rule as Atrium D-022).
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: user, cross-spec decision (2026-10-29)

## D-022: How should the 'Impex works without Atrium' proof run (Q-1)?
- Choice: Both: in-process WithoutAtrium suite inside composer test, plus a CI job that uninstalls Atrium
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-023: Where does the run link render relative to Showroom's flow-name cell (Q-2)?
- Choice: Accept the link as a separate row.actions fragment, with standard slots only
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-024: Should the three workstreams (run link, 10-screen declarations, Atrium to suggest plus WithoutAtrium suite) ship as one spec and PR?
- Choice: One spec and one PR, merged together with Atrium, Keen and Showroom
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-025: Q-1. How should the package prove it runs without Atrium?
- Choice: Both
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-026: Q-2. Where does the run link render in Showroom's table?
- Choice: (a) A separate row.actions fragment beside the plain-text flow name (standard slots only)
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-027: How should a malformed run_link.screens entry behave?
- Choice: Skip the entry and log it, rethrow only in debug mode
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-028: Q-1: Run the no-Atrium proof in process only, in a CI lane only, or both?
- Choice: Both: in-process PSR-4 removal inside `composer test`, plus a CI lane with Atrium uninstalled
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-029: Q-2: Where does the run link sit in Showroom's row, given that criterion 6 asserts a link in the row's `row.actions` slot?
- Choice: (a) Accept a separate `row.actions` fragment beside the plain-text flow name
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-025: Which Atrium `conflict` constraint should Impex ship (build question)?
- Choice: Keep `<0.1.0` across the suite; criterion 12's `<1.0.0` fallback text is replaced.
- Rationale: Already decided by D-020 and the suite rule ("conflict refactor-circus/atrium <0.1.0" in every package). Atrium's `v0.1.0` CHANGELOG section is an untagged placeholder; the registry sits under Unreleased, so the first tag (v0.1.0) carries it.
- Date: 2026-10-30
- Raised by: build (group 1)
