# Standards for Register Showroom run link

| Standard | File | Source | Why it applies |
| --- | --- | --- | --- |
| architecture/dependencies | vendor/refactor-circus/standards/standards/architecture/dependencies.md | refactor-circus/standards@dev-main | Atrium moves to suggest; the integration is config-only and guarded by class_exists() |
| architecture/configuration | vendor/refactor-circus/standards/standards/architecture/configuration.md | refactor-circus/standards@dev-main | New `impex.atrium.run_link.screens` key with a banner and defaults |
| architecture/public-api | vendor/refactor-circus/standards/standards/architecture/public-api.md | refactor-circus/standards@dev-main | New `impex.run-link` section key and the screen keys |
| architecture/service-providers | vendor/refactor-circus/standards/standards/architecture/service-providers.md | refactor-circus/standards@dev-main | Screen registration from boot(), behind the Atrium guard |
| architecture/naming | vendor/refactor-circus/standards/standards/architecture/naming.md | refactor-circus/standards@dev-main | New `Atrium\ScreenRegistrations` and `Atrium\Screens` classes and screen keys |
| http/authorization | vendor/refactor-circus/standards/standards/http/authorization.md | refactor-circus/standards@dev-main | The link and the screens' access() closures go through Impex's ScreenAccess and policies |
| models/models | vendor/refactor-circus/standards/standards/models/models.md | refactor-circus/standards@dev-main | Unsaved FlowOverrideModel and ChannelModel stand-ins as row records; no new models |
| testing/arch-tests | vendor/refactor-circus/standards/standards/testing/arch-tests.md | refactor-circus/standards@dev-main | New arch rule: only src/Atrium uses Atrium |
| testing/test-layers | vendor/refactor-circus/standards/standards/testing/test-layers.md | refactor-circus/standards@dev-main | UI tests cover wiring through a workbench test host; a WithoutAtrium suite covers the API and MCP |
