VERDICT: PASS

Security review, round 1. No findings at or above minor.

- Authorization: all three endpoints authorize viewAny on RunModel through the Keystone Request (401 guest, 403 refused); stats gates message counts on viewAny MessageModel.
- Scoping: runs and messages are scoped with whereOwnedBy when a viewer exists (stats, search).
- Injection: search escapes %, _ and the escape char, binds values, uses a fixed escape literal; no user input in raw SQL.
- Validation: q trimmed, required, max 255; limit 1-25 via Data rules and fromValidated.
- Data exposure: settings summary reads a fixed whitelist with casts; no secrets, host-added keys not sent.
- Scramble and docs routes are workbench-only; CI workflow adds no secrets or elevated permissions.
