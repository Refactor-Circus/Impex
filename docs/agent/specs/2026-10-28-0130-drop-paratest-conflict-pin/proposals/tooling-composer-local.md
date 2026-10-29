# Composer Local Manifest

In a checkout or job worktree installed from `composer.local.json`, change dependencies only through `composer local:install` / `composer local:update`, never a bare `composer update`.

## Why

`bin/composer-local.php` builds `composer.local.json` from `composer.json` and adds the sibling checkouts as path repositories. The `local:*` scripts then run Composer against that file (`@putenv COMPOSER=composer.local.json`). `composer.lock`, `composer.local.json` and `composer.local.lock` are all gitignored. A bare `composer update` resolves `composer.json` with no lock, so it re-resolves every package and replaces the sibling symlinks with VCS clones.

## Rules

- After any edit to `composer.json`, run `composer local:update` (or `local:install`) so `composer.local.json` is rebuilt before the next install, test or build. A stale `composer.local.json` is a failed pre-flight, not a warning.
- Limit an update to named packages by passing them after `--`: `composer local:update -- vendor/package -W`.
- Never hand-edit `composer.local.json`. It is generated.
- Specs and tasks name the `local:*` command, not a bare `composer update`. CI resolves `refactor-circus/*` from VCS, so a moved symlink label is not a code change.

## Example

```bash
# composer.json: pestphp/pest ^5.3.1 -> ^5.3.4, conflict key removed
composer local:update -- pestphp/pest brianium/paratest -W
composer show pestphp/pest brianium/paratest
```
