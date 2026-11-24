<?php

declare(strict_types=1);

/*
 * Impex ships its Atrium front-end module: the sources in `atrium/` build
 * (`pnpm -C atrium build`) into the committed `dist/atrium/`, which
 * composer.json's `extra.atrium` declares. Each build writes `build.json`
 * with the SHA-256 of the files it was built from; this recomputes it the way
 * the Atrium preset's `hashInputs()` does, so a source changed without a
 * rebuild fails here with no Node needed. CI also rebuilds and diffs.
 */

function atriumModuleRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @return array{key: string, shared_api: string, root: string, inputs: list<string>, hash: string}
 */
function atriumModuleBuild(): array
{
    /** @var array{key: string, shared_api: string, root: string, inputs: list<string>, hash: string} $info */
    $info = json_decode((string) file_get_contents(atriumModuleRoot().'/dist/atrium/build.json'), true, flags: JSON_THROW_ON_ERROR);

    return $info;
}

/**
 * The SHA-256 of `inputs` under `root`, as the preset's `hashInputs()` takes it.
 *
 * @param  list<string>  $inputs
 */
function atriumModuleSourcesHash(string $root, array $inputs): string
{
    $files = [];

    $walk = function (string $path) use (&$walk, &$files, $root): void {
        $absolute = $root.'/'.$path;

        if (! file_exists($absolute)) {
            return;
        }

        if (is_dir($absolute)) {
            foreach (scandir($absolute) ?: [] as $entry) {
                if (! in_array($entry, ['node_modules', 'dist', '.DS_Store', '.git'], true) && ! str_starts_with($entry, '.')) {
                    $walk($path.'/'.$entry);
                }
            }

            return;
        }

        $files[] = $path;
    };

    foreach ($inputs as $input) {
        $walk(rtrim($input, '/'));
    }

    $files = array_values(array_unique($files));
    sort($files, SORT_STRING);

    $lines = array_map(
        fn (string $path): string => $path."\0".hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($root.'/'.$path)))."\n",
        $files,
    );

    return hash('sha256', implode('', $lines));
}

it('ships the built module files composer.json declares', function (string $kind): void {
    /** @var array<string, mixed> $composer */
    $composer = json_decode((string) file_get_contents(atriumModuleRoot().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    /** @var array<string, list<string>> $atrium */
    $atrium = $composer['extra']['atrium'];

    expect($atrium[$kind])->not->toBeEmpty();

    foreach ($atrium[$kind] as $path) {
        expect(atriumModuleRoot().'/'.$path)->toBeFile();
    }
})->with(['scripts', 'styles']);

it('points extra.atrium at dist/atrium/atrium.js and atrium.css', function (): void {
    /** @var array<string, mixed> $composer */
    $composer = json_decode((string) file_get_contents(atriumModuleRoot().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['atrium']['scripts'])->toBe(['dist/atrium/atrium.js'])
        ->and($composer['extra']['atrium']['styles'])->toBe(['dist/atrium/atrium.css']);
});

it('was built for the impex key and shared API 1.0 from the atrium folder', function (): void {
    $info = atriumModuleBuild();

    expect($info['key'])->toBe('impex')
        ->and($info['shared_api'])->toBe('1.0')
        ->and(realpath(atriumModuleRoot().'/dist/atrium/'.$info['root']))->toBe(realpath(atriumModuleRoot().'/atrium'));
});

it('was built from the module sources as they are now', function (): void {
    $info = atriumModuleBuild();
    $root = realpath(atriumModuleRoot().'/dist/atrium/'.$info['root']);

    expect($root)->toBeString()
        ->and(atriumModuleSourcesHash((string) $root, $info['inputs']))
        ->toBe($info['hash'], 'The Atrium module build is older than its sources: run `pnpm -C atrium build` and commit dist/atrium.');
});

it('keeps the module sources out of the dist archive, but ships dist', function (): void {
    $attributes = (string) file_get_contents(atriumModuleRoot().'/.gitattributes');

    expect($attributes)->toMatch('#^/atrium\s+export-ignore$#m')
        ->not->toMatch('#^/dist\S*\s+export-ignore#m');
});

it('points extra.atrium.integration at the built impex manifest', function (): void {
    /** @var array<string, mixed> $composer */
    $composer = json_decode((string) file_get_contents(atriumModuleRoot().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['atrium']['integration'])->toBe('dist/atrium/integration.json');

    $path = atriumModuleRoot().'/'.$composer['extra']['atrium']['integration'];

    expect($path)->toBeFile();

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['key'])->toBe('impex')
        ->and($manifest['version'])->toBe(1);
});
