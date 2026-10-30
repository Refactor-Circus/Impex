<?php

declare(strict_types=1);

/*
 * The docs promise what the run link and the move of Atrium to `suggest`
 * changed: the README, the dashboard doc and the Boost skill document the
 * config key and the section key, and the CHANGELOG carries the upgrade note.
 */

function packageDoc(string $path): string
{
    $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

    expect($contents)->toBeString();

    return (string) $contents;
}

it('documents the run link and Atrium as a suggestion in the README', function (): void {
    expect(packageDoc('README.md'))
        ->toContain('run_link.screens')
        ->toContain('impex.run-link')
        ->toContain('suggest')
        ->toContain('without-atrium');
});

it('documents the run link in the dashboard doc and the Boost skill', function (string $path): void {
    expect(packageDoc($path))
        ->toContain('impex.atrium.run_link.screens')
        ->toContain('impex.run-link')
        ->toContain('suggest');
})->with([
    'docs/11-dashboard.md',
    'resources/boost/skills/impex-development/SKILL.md',
]);

it('carries the upgrade note in the CHANGELOG', function (): void {
    expect(packageDoc('CHANGELOG.md'))
        ->toContain('suggest')
        ->toContain('conflict')
        ->toContain('<0.1.0')
        ->toContain('run_link.screens')
        ->toContain('Keen')
        ->toContain('Showroom');
});
