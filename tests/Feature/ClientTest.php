<?php

declare(strict_types=1);

/*
 * client/ is the committed TypeScript client (@refactor-circus/impex-client)
 * generated from openapi.json. `composer client:generate` rebuilds it, and
 * client/openapi.sha256 records the document it was generated from
 * (criteria 17, 18 and 19).
 */

function clientRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @return array<string, mixed>
 */
function clientJson(string $path): array
{
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) file_get_contents(clientRoot().'/'.$path), true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * @return array<string, mixed>
 */
function clientPackageSection(string $section): array
{
    /** @var array<string, mixed> $entries */
    $entries = clientJson('client/package.json')[$section] ?? [];

    return $entries;
}

it('names the client package @refactor-circus/impex-client 2.0.0, an ES module', function (): void {
    $package = clientJson('client/package.json');

    expect($package['name'] ?? null)->toBe('@refactor-circus/impex-client')
        ->and($package['version'] ?? null)->toBe('2.0.0')
        ->and($package['type'] ?? null)->toBe('module');
});

it('depends on openapi-fetch at runtime', function (): void {
    expect(clientPackageSection('dependencies'))->toHaveKey('openapi-fetch');
});

it('pins the generator and the compiler to exact versions', function (string $dependency): void {
    $version = clientPackageSection('devDependencies')[$dependency] ?? null;

    expect($version)->toBeString()
        ->and($version)->toMatch('/^\d+\.\d+\.\d+$/');
})->with(['openapi-typescript', 'typescript']);

it('has the generate and typecheck scripts', function (): void {
    expect(clientPackageSection('scripts'))->toHaveKeys(['generate', 'typecheck']);
});

it('commits the lockfile, the compiler config, the schema and the readme', function (string $path): void {
    expect(clientRoot().'/client/'.$path)->toBeFile();
})->with(['package-lock.json', 'tsconfig.json', 'src/schema.d.ts', 'README.md']);

it('exports createImpexClient and the schema types', function (): void {
    $index = (string) file_get_contents(clientRoot().'/client/src/index.ts');

    expect($index)->toMatch('/export\s+function\s+createImpexClient\s*\(/')
        ->and($index)->toMatch('/export\s+type\s*\{[^}]*\bpaths\b[^}]*\}\s*from\s*[\'"]\.\/schema/')
        ->and($index)->toMatch('/export\s+type\s*\{[^}]*\bcomponents\b[^}]*\}\s*from\s*[\'"]\.\/schema/')
        ->and($index)->toMatch('/export\s+type\s*\{[^}]*\boperations\b[^}]*\}\s*from\s*[\'"]\.\/schema/');
});

it('records the sha256 of the committed openapi.json', function (): void {
    $recorded = trim((string) file_get_contents(clientRoot().'/client/openapi.sha256'));

    expect($recorded)->toBe(hash_file('sha256', clientRoot().'/openapi.json'), 'client/openapi.sha256 is stale: run composer client:generate.');
});

it('regenerates the document, the schema and the checksum in order', function (): void {
    /** @var array<string, mixed> $scripts */
    $scripts = clientJson('composer.json')['scripts'] ?? [];

    expect($scripts['client:generate'] ?? null)->toBe([
        '@php vendor/bin/testbench scramble:export --path=openapi.json',
        'npm --prefix client ci',
        'npm --prefix client run generate',
        "@php -r \"file_put_contents('client/openapi.sha256', hash_file('sha256', 'openapi.json').PHP_EOL);\"",
    ]);
});
