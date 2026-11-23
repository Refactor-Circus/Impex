<?php

declare(strict_types=1);

/*
 * The docs promise what v2.0.0 changed: Impex is a JSON API plus an MCP
 * server, described by `openapi.json` and the `@refactor-circus/impex-client`
 * package, with Atrium v2 as the optional UI. The CHANGELOG carries the
 * upgrade note, and no doc still tells a host to install or configure Atrium
 * through Impex.
 */

function packageDoc(string $path): string
{
    $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

    expect($contents)->toBeString();

    return (string) $contents;
}

it('describes the API, the OpenAPI document, the client and Atrium v2 in the README', function (): void {
    expect(packageDoc('README.md'))
        ->toContain('openapi.json')
        ->toContain('@refactor-circus/impex-client')
        ->toContain('client:generate')
        ->toContain('impex/stats')
        ->toContain('impex/search')
        ->toContain('impex/settings-summary')
        ->toContain('Atrium v2');
});

it('drops the Atrium install and config instructions from the README', function (): void {
    expect(packageDoc('README.md'))
        ->not->toContain('composer require refactor-circus/atrium')
        ->not->toContain('impex.atrium.')
        ->not->toContain('without-atrium');
});

it('carries the v2.0.0 upgrade note in the CHANGELOG', function (): void {
    expect(packageDoc('CHANGELOG.md'))
        ->toContain('## [v2.0.0]')
        ->toContain('impex.ui.enabled')
        ->toContain('impex.atrium.features')
        ->toContain('impex.atrium.show_all')
        ->toContain('impex.atrium.run_link.screens')
        ->toContain('atrium.integrations.impex')
        ->toContain('ImpexSupportFeature')
        ->toContain('impex-views')
        ->toContain('impex-lang')
        ->toContain('D-006')
        ->toContain('impex/stats')
        ->toContain('impex/search')
        ->toContain('impex/settings-summary')
        ->toContain('@refactor-circus/impex-client');
});

it('keeps the v2.0.0 note above the v0.1.0 release', function (): void {
    $changelog = packageDoc('CHANGELOG.md');

    expect(strpos($changelog, '## [v2.0.0]'))->toBeLessThan(strpos($changelog, '## [v0.1.0]'));
});

it('names the client regeneration command and no Atrium layer in the agent docs', function (string $path): void {
    expect(packageDoc($path))
        ->toContain('client:generate')
        ->toContain('openapi.json')
        ->not->toContain('src/Atrium')
        ->not->toContain('registerAtriumPlugin')
        ->not->toContain('impex.atrium.')
        ->not->toContain('without-atrium');
})->with([
    'AGENTS.md',
    'resources/boost/skills/impex-development/SKILL.md',
]);

it('documents the three dashboard endpoints and the client in the API doc', function (): void {
    expect(packageDoc('docs/09-api.md'))
        ->toContain('GET impex/stats')
        ->toContain('impex.stats.show')
        ->toContain('GET impex/search')
        ->toContain('impex.search.index')
        ->toContain('GET impex/settings-summary')
        ->toContain('impex.settings-summary.show')
        ->toContain('openapi.json')
        ->toContain('@refactor-circus/impex-client')
        ->toContain('client:generate');
});

it('points the dashboard doc at Atrium v2', function (): void {
    expect(packageDoc('docs/11-dashboard.md'))
        ->toContain('Atrium v2')
        ->toContain('atrium.integrations.impex')
        ->toContain('@refactor-circus/impex-client')
        ->not->toContain('composer require refactor-circus/atrium')
        ->not->toContain('impex.atrium.')
        ->not->toContain('impexCan')
        ->not->toContain('without-atrium');
});

it('drops the Atrium keys and steps from the remaining docs', function (string $path): void {
    expect(packageDoc($path))
        ->not->toContain('composer require refactor-circus/atrium')
        ->not->toContain('impex.atrium.')
        ->not->toContain('impex.ui.enabled')
        ->not->toContain('atrium-assets')
        ->not->toContain('viewAtrium')
        ->not->toContain('impex-views')
        ->not->toContain('impex-lang')
        ->not->toContain('ImpexSupportFeature');
})->with([
    'README.md',
    'AGENTS.md',
    'resources/boost/skills/impex-development/SKILL.md',
    'docs/README.md',
    'docs/01-installation.md',
    'docs/08-ownership.md',
    'docs/09-api.md',
    'docs/11-dashboard.md',
    'docs/13-configuration.md',
]);

it('documents no ui or atrium config section', function (): void {
    expect(packageDoc('docs/13-configuration.md'))
        ->not->toContain('## `ui`')
        ->not->toContain('## `atrium`')
        ->toContain('openapi.json');
});
