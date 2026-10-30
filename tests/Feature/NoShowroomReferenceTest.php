<?php

declare(strict_types=1);

/*
 * Impex knows no Showroom: it places its run link on Showroom's transfers
 * rows only through the editable default in config/impex.php. The code in
 * src/ (comments excluded, so documentation examples may name other
 * packages' streams) never names a Showroom class or screen.
 */

/**
 * Every PHP file under src/ with its comments removed, by path.
 *
 * @return array<string, string>
 */
function impexSourceWithoutComments(): array
{
    $sources = [];
    $root = dirname(__DIR__, 2).'/src';

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $code = '';

        foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        // A namespace in a string is written with doubled backslashes.
        // Keyed by forward-slash path, on Windows too.
        $sources[str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = str_replace('\\\\', '\\', $code);
    }

    return $sources;
}

it('names no showroom class or screen in its code', function (): void {
    $sources = impexSourceWithoutComments();

    expect($sources)->not->toBeEmpty();

    $offending = array_keys(array_filter(
        $sources,
        fn (string $code): bool => str_contains($code, 'RefactorCircus\\Showroom') || str_contains($code, 'showroom.transfers'),
    ));

    expect($offending)->toBe([]);
});

it('keeps comments out of the scan, such as the stream name example', function (): void {
    $stream = 'Domains/Subscription/Contracts/Stream.php';

    expect((string) file_get_contents(dirname(__DIR__, 2).'/src/'.$stream))->toContain('showroom.products')
        ->and(impexSourceWithoutComments()[$stream])->not->toContain('showroom.products');
});
