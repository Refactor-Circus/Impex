VERDICT: PASS

Standards review, round 1. No findings at or above minor.

- dependencies: Atrium moved to require-dev and suggest, with a conflict. Atrium classes are named only in src/Atrium, and the provider guards with a class_exists() string and a strict impex.ui.enabled === true check.
- configuration: run_link.screens has a banner, a safe default and a fallback for an older published config.
- service-providers: registerAtriumScreens() is a private method called from boot() behind the strict guard.
- models: the stand-in models have fillable slug and name.
- arch-tests: the new rule is in place.
- naming, public-api, authorization: the new classes and keys follow the conventions. Access goes through ScreenAccess.
