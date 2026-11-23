<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

/*
 * Impex 2.0 ships its JSON API and MCP server only: the dashboard moved to
 * Atrium v2, which calls the API (criteria 10, 11 and 12).
 */

it('registers no route under the old dashboard names', function (): void {
    $names = array_values(array_filter(
        array_map(fn (Route $route): ?string => $route->getName(), iterator_to_array(app('router')->getRoutes())),
        fn (?string $name): bool => $name !== null && str_starts_with($name, 'atrium.impex.'),
    ));

    expect($names)->toBe([]);
});

it('ships no dashboard code, views or translations', function (string $path): void {
    expect(dirname(__DIR__, 2).'/'.$path)->not->toBeDirectory();
})->with([
    'src/Atrium',
    'resources/views',
    'lang',
]);

it('names atrium nowhere in its source or config', function (): void {
    $root = dirname(__DIR__, 2);

    $offenders = [];

    foreach (Finder::create()->files()->in([$root.'/src', $root.'/config']) as $file) {
        if (stripos((string) file_get_contents($file->getPathname()), 'atrium') !== false) {
            $offenders[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }

    expect($offenders)->toBe([]);
});

it('has no ui or atrium config, and keeps the cortex and routes keys', function (): void {
    expect(config('impex.ui'))->toBeNull()
        ->and(config('impex.atrium'))->toBeNull()
        ->and(config('impex.cortex'))->toBe([
            'enabled' => true,
            'server' => 'impex',
            'tools' => null,
        ])
        ->and(config('impex.routes'))->toBe([
            'enabled' => true,
            'prefix' => 'impex',
            'middleware' => ['api'],
            'channel_middleware' => ['api'],
            'subscriber_middleware' => ['api'],
        ]);
});

it('no longer has the support feature class', function (): void {
    expect(class_exists('RefactorCircus\Impex\Atrium\Features\ImpexSupportFeature'))->toBeFalse();
});

it('publishes no views or translations', function (): void {
    expect(ServiceProvider::publishableGroups())
        ->toContain('impex-config')
        ->not->toContain('impex-views')
        ->not->toContain('impex-lang');
});
