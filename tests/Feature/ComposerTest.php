<?php

declare(strict_types=1);

/*
 * Impex requires only Keystone from the suite. Atrium is suggested, installed
 * for development, and refused when it is older than the screen-extensions
 * registry; Showroom is never a dependency.
 */

/**
 * @return array<string, mixed>
 */
function impexComposer(): array
{
    /** @var array<string, mixed> $composer */
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    return $composer;
}

/**
 * @return array<string, string>
 */
function impexComposerSection(string $section): array
{
    /** @var array<string, string> $entries */
    $entries = impexComposer()[$section] ?? [];

    return $entries;
}

it('requires keystone and no other suite package', function (): void {
    $suite = array_values(array_filter(
        array_keys(impexComposerSection('require')),
        fn (string $package): bool => str_starts_with($package, 'refactor-circus/'),
    ));

    expect($suite)->toBe(['refactor-circus/keystone']);
});

it('suggests atrium, naming the registry release as the minimum and keen for history', function (): void {
    $suggest = impexComposerSection('suggest');

    expect($suggest)->toHaveKey('refactor-circus/atrium')
        ->and($suggest['refactor-circus/atrium'])->toContain('screen-extensions registry')
        ->and($suggest['refactor-circus/atrium'])->toContain('0.1.0')
        ->and($suggest['refactor-circus/atrium'])->toContain('refactor-circus/keen')
        ->and($suggest['refactor-circus/atrium'])->toContain('History');
});

it('installs atrium for development on its main branch', function (): void {
    expect(impexComposerSection('require-dev'))->toHaveKey('refactor-circus/atrium', 'dev-main');
});

it('refuses an atrium older than the screen-extensions registry', function (): void {
    expect(impexComposerSection('conflict'))->toHaveKey('refactor-circus/atrium', '<0.1.0');
});

it('never depends on showroom', function (): void {
    foreach (['require', 'require-dev', 'suggest'] as $section) {
        expect(impexComposerSection($section))->not->toHaveKey('refactor-circus/showroom');
    }
});

it('runs the no-atrium suite inside composer test, after the unit suite', function (): void {
    /** @var array<string, list<string>> $scripts */
    $scripts = impexComposer()['scripts'];

    $test = $scripts['test'];
    $unit = array_search('@test:unit', $test, true);
    $withoutAtrium = array_search('@test:without-atrium', $test, true);

    expect($scripts)->toHaveKey('test:without-atrium')
        ->and($unit)->toBeInt()
        ->and($withoutAtrium)->toBe($unit + 1);
});
