<?php

declare(strict_types=1);

/*
 * Impex requires only Keystone from the suite. The dashboard is Atrium v2's,
 * so neither Atrium nor PennantPlus is a dependency, a suggestion or a
 * conflict, and Showroom never is (criterion 13).
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
 * @return array<string, mixed>
 */
function impexComposerSection(string $section): array
{
    /** @var array<string, mixed> $entries */
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

it('never names atrium as a dependency, suggestion or conflict', function (string $section): void {
    expect(impexComposerSection($section))->not->toHaveKey('refactor-circus/atrium');
})->with(['require', 'require-dev', 'suggest', 'conflict']);

it('registers no atrium plugin', function (): void {
    expect(impexComposerSection('extra'))->not->toHaveKey('atrium');
});

it('never names pennantplus as a dev dependency or suggestion', function (string $section): void {
    expect(impexComposerSection($section))->not->toHaveKey('refactor-circus/pennantplus');
})->with(['require-dev', 'suggest']);

it('never depends on showroom', function (string $section): void {
    expect(impexComposerSection($section))->not->toHaveKey('refactor-circus/showroom');
})->with(['require', 'require-dev', 'suggest']);

it('has no no-atrium suite, and composer test does not run one', function (): void {
    /** @var array<string, list<string>> $scripts */
    $scripts = impexComposerSection('scripts');

    expect($scripts)->not->toHaveKey('test:without-atrium')
        ->and($scripts['test'])->not->toContain('@test:without-atrium')
        ->and($scripts['test'])->toContain('@test:unit');
});

it('exports the OpenAPI document with scramble, a development dependency', function (): void {
    expect(impexComposerSection('require-dev'))->toHaveKey('dedoc/scramble')
        ->and(impexComposerSection('require'))->not->toHaveKey('dedoc/scramble');
});
