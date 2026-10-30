<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Impex\ImpexServiceProvider;

/*
 * Impex places its run link on other packages' screens only through
 * `impex.atrium.run_link.screens`: one `impex.run-link` section per entry,
 * registered from boot() while Atrium is installed and the UI is on.
 */

/**
 * Forget every registration and run Impex's screen registration again, as
 * boot() does, with the config as the test left it.
 */
function reregisterImpexScreens(): void
{
    app(ScreenExtensions::class)->flush();

    (fn () => $this->registerAtriumScreens())->call(new ImpexServiceProvider(app()));
}

/**
 * @return list<string>
 */
function sectionKeys(string $target, string $slot): array
{
    return array_map(fn (SectionDefinition $section): string => $section->key, app(ScreenExtensions::class)->sections($target, $slot));
}

it('ships the showroom transfers rows as the default screen', function (): void {
    $shipped = (require dirname(__DIR__, 3).'/config/impex.php')['atrium']['run_link']['screens'];

    expect($shipped)->toBe(['showroom.transfers' => 'row.actions'])
        ->and(config('impex.atrium.run_link.screens'))->toBe(['showroom.transfers' => 'row.actions']);
});

it('registers the run link on the default screen from boot', function (): void {
    $sections = app(ScreenExtensions::class)->sections('showroom.transfers', 'row.actions');

    expect($sections)->toHaveCount(1)
        ->and($sections[0]->key)->toBe('impex.run-link')
        ->and($sections[0]->label)->toBe('Run')
        ->and($sections[0]->view)->toBe('impex::ui.screens.run-link')
        ->and($sections[0]->public)->toBeFalse();
});

it('falls back to the shipped default when the key is missing from a published config', function (): void {
    $atrium = config('impex.atrium');
    unset($atrium['run_link']);
    config()->set('impex.atrium', $atrium);

    reregisterImpexScreens();

    expect(sectionKeys('showroom.transfers', 'row.actions'))->toBe(['impex.run-link']);
});

it('falls back to the shipped default when the screens key is null', function (): void {
    config()->set('impex.atrium.run_link.screens', null);

    reregisterImpexScreens();

    expect(sectionKeys('showroom.transfers', 'row.actions'))->toBe(['impex.run-link']);
});

it('registers nothing for an explicit empty list', function (): void {
    config()->set('impex.atrium.run_link.screens', []);

    reregisterImpexScreens();

    expect(sectionKeys('showroom.transfers', 'row.actions'))->toBe([]);
});

it('registers only the screens of a custom list', function (): void {
    config()->set('impex.atrium.run_link.screens', ['acme.jobs' => 'aside']);

    reregisterImpexScreens();

    expect(sectionKeys('acme.jobs', 'aside'))->toBe(['impex.run-link'])
        ->and(sectionKeys('showroom.transfers', 'row.actions'))->toBe([]);
});

it('registers nothing while the ui is disabled', function (mixed $enabled): void {
    config()->set('impex.ui.enabled', $enabled);

    reregisterImpexScreens();

    expect(sectionKeys('showroom.transfers', 'row.actions'))->toBe([]);
})->with([
    'false' => [false],
    'a truthy string' => ['1'],
]);

it('throws for a malformed entry in testing', function (mixed $screens): void {
    config()->set('impex.atrium.run_link.screens', $screens);

    expect(fn () => reregisterImpexScreens())->toThrow(InvalidArgumentException::class);
})->with([
    'an integer key' => [[5 => 'row.actions']],
    'an empty key' => [['' => 'row.actions']],
    'a null slot' => [['acme.jobs' => null]],
    'an empty slot' => [['acme.jobs' => '']],
    'an unknown slot' => [['acme.jobs' => 'Not A Slot!']],
    'a key that is no screen' => [['Not A Screen' => 'aside']],
    'a list instead of a map' => ['showroom.transfers'],
]);

it('throws for a malformed entry in local', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    config()->set('impex.atrium.run_link.screens', ['acme.jobs' => null]);

    expect(fn () => reregisterImpexScreens())->toThrow(InvalidArgumentException::class);
});

it('reports a malformed entry once in production and registers the valid one', function (): void {
    Exceptions::fake();
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('impex.atrium.run_link.screens', [5 => 'row.actions', 'acme.jobs' => 'aside']);

    reregisterImpexScreens();

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(InvalidArgumentException::class);

    expect(sectionKeys('acme.jobs', 'aside'))->toBe(['impex.run-link']);
});
